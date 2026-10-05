{{-- email-accounts/show.blade.php --}}
@php
    $user = auth()->user();
    $isDeveloper  = $user->isDeveloper();
    $isAdmin      = $user->isAdmin();
    $isSuperAdmin = $user->isSuperAdmin();
    $isLandlord   = $user->isLandlord();
    $isTenant     = $user->isTenant();

    // ── Role-aware routing / layout ────────────────────────────
    if ($isSuperAdmin) {
        $routeNamePrefix = 'super-admin.email-accounts';
        $urlPath         = 'super-admin/email-accounts';
        $layout          = 'layouts.app';
        $pageTitle       = 'Email Account - Super Admin';
    } elseif ($isDeveloper) {
        $routeNamePrefix = 'developer.email-accounts';
        $urlPath         = 'developer/email-accounts';
        $layout          = 'layouts.dev';
        $pageTitle       = 'Email Account - Developer';
    } elseif ($isAdmin) {
        $routeNamePrefix = 'admin.email-accounts';
        $urlPath         = 'admin/email-accounts';
        $layout          = 'layouts.app';
        $pageTitle       = 'Email Account - Admin';
    } elseif ($isLandlord) {
        $routeNamePrefix = 'landlord.email-accounts';
        $urlPath         = 'landlord/email-accounts';
        $layout          = 'layouts.landlord';
        $pageTitle       = 'Email Account';
    } elseif ($isTenant) {
        $routeNamePrefix = 'tenant.email-accounts';
        $urlPath         = 'tenant/email-accounts';
        $layout          = 'layouts.tenant';
        $pageTitle       = 'Email Account';
    } else {
        $routeNamePrefix = 'email-accounts';
        $urlPath         = 'email-accounts';
        $layout          = 'layouts.app';
        $pageTitle       = 'Email Account';
    }

    $backRoute = route($routeNamePrefix . '.index');

    $emailAccount = $emailAccount ?? null;

    // ── Stats (efficient — one query each, cached per request) ─
    $stats = [
        'total'   => $emailAccount ? $emailAccount->emails()->count() : 0,
        'inbox'   => $emailAccount ? $emailAccount->emails()->where('folder', 'INBOX')->count() : 0,
        'unread'  => $emailAccount ? $emailAccount->emails()->where('folder', 'INBOX')->where('is_read', false)->count() : 0,
        'sent'    => $emailAccount ? $emailAccount->emails()->where('folder', 'SENT')->count() : 0,
        'drafts'  => $emailAccount ? $emailAccount->emails()->where('folder', 'DRAFTS')->count() : 0,
        'trash'   => $emailAccount ? $emailAccount->emails()->where('folder', 'TRASH')->count() : 0,
    ];

    // ── Recent messages (paginated, 15/page) ──────────────────
    $currentFolder = request()->get('folder', 'INBOX');
    $search        = trim((string) request()->get('q', ''));

    $emails = $emailAccount
        ? $emailAccount->emails()
            ->where('folder', $currentFolder)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('subject', 'like', "%{$search}%")
                      ->orWhere('from_email', 'like', "%{$search}%")
                      ->orWhere('from_name',  'like', "%{$search}%")
                      ->orWhere('body',       'like', "%{$search}%");
                });
            })
            ->orderByDesc('received_at')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
        : collect();

    // Optional: currently selected email (via ?email=123)
    $selectedEmail = null;
    if ($emailAccount && request()->filled('email')) {
        $selectedEmail = $emailAccount->emails()
            ->where('id', (int) request('email'))
            ->first();
    }

    $errorMessage   = session('error');
    $successMessage = session('success');
    $warningMessage = session('warning');

    // ── Small formatting helper ────────────────────────────────
    if (!function_exists('formatEmailDate')) {
        function formatEmailDate($date) {
            if (!$date) return 'N/A';
            $diff = now()->diff($date);
            if ($diff->days > 7)  return $date->format('M j, Y');
            if ($diff->days > 0)  return $diff->days . 'd';
            if ($diff->h > 0)     return $diff->h . 'h';
            if ($diff->i > 0)     return $diff->i . 'm';
            return 'Just now';
        }
    }
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- ═════════════════════════════════════════════════════════ --}}
    {{-- HEADER                                                    --}}
    {{-- ═════════════════════════════════════════════════════════ --}}
    <div class="card">
        <div class="flex flex-wrap justify-between items-center p-6">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                     style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-color: var(--primary);">
                    <i class="fas fa-envelope text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-envelope mr-2" style="color: var(--primary);"></i>
                        {{ $pageTitle }}
                    </h2>
                    <div class="text-sm flex flex-wrap items-center mt-1 gap-2" style="color: var(--text-secondary);">
                        @if($emailAccount)
                            <span><i class="fas fa-at mr-1"></i> {{ $emailAccount->email }}</span>
                            @if($emailAccount->is_primary)
                                <span class="text-xs px-1.5 py-0.5 rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                    <i class="fas fa-star mr-0.5"></i> Primary
                                </span>
                            @endif
                            @if($emailAccount->is_connected)
                                <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                    <i class="fas fa-circle text-[6px] mr-1"></i> Connected
                                </span>
                            @else
                                <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                    <i class="fas fa-circle text-[6px] mr-1"></i> Disconnected
                                </span>
                            @endif
                        @else
                            <span><i class="fas fa-info-circle mr-1"></i> Account not found</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="text-sm mt-2 sm:mt-0 flex items-center gap-3" style="color: var(--text-secondary);">
                <span class="hidden sm:inline"><i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}</span>
                <a href="{{ $backRoute }}"
                   class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Accounts
                </a>
            </div>
        </div>
    </div>

    {{-- ═════════════════════════════════════════════════════════ --}}
    {{-- FLASH MESSAGES                                            --}}
    {{-- ═════════════════════════════════════════════════════════ --}}
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

    {{-- ═════════════════════════════════════════════════════════ --}}
    {{-- NO ACCOUNT FALLBACK                                       --}}
    {{-- ═════════════════════════════════════════════════════════ --}}
    @if(!$emailAccount)
        <div class="card">
            <div class="text-center py-12">
                <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4"
                     style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-exclamation-triangle text-2xl" style="color: var(--warning);"></i>
                </div>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                    Email Account Not Found
                </h4>
                <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                    The email account you are looking for does not exist or you do not have access to it.
                </p>
                <a href="{{ $backRoute }}"
                   class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-white transition-all hover:scale-105"
                   style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Accounts
                </a>
            </div>
        </div>
    @else

    {{-- ═════════════════════════════════════════════════════════ --}}
    {{-- MAIN LAYOUT: Sidebar (info) + Mail Pane                  --}}
    {{-- ═════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- ─── Sidebar: account + stats + servers ──────────────── --}}
        <div class="lg:col-span-4 space-y-6">

            {{-- Account Information --}}
            <div class="card">
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-user-circle mr-2" style="color: var(--primary);"></i> Account
                        </h3>
                        <div class="flex gap-2">
                            <a href="{{ route($routeNamePrefix . '.edit', $emailAccount->id) }}"
                               class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium transition-all hover:scale-105"
                               style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </a>
                            @if(!$emailAccount->is_primary)
                                <button type="button" onclick="setPrimary({{ $emailAccount->id }})"
                                        class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium transition-all hover:scale-105"
                                        style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                    <i class="fas fa-star mr-1"></i> Set Primary
                                </button>
                            @endif
                        </div>
                    </div>

                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-4 py-1.5">
                            <dt style="color: var(--text-secondary);">Email</dt>
                            <dd class="font-medium truncate" style="color: var(--text-primary);">{{ $emailAccount->email }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-1.5">
                            <dt style="color: var(--text-secondary);">Display Name</dt>
                            <dd class="font-medium truncate" style="color: var(--text-primary);">{{ $emailAccount->display_name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-1.5">
                            <dt style="color: var(--text-secondary);">Provider</dt>
                            <dd class="font-medium" style="color: var(--text-primary);">{{ ucfirst($emailAccount->provider) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-1.5">
                            <dt style="color: var(--text-secondary);">Status</dt>
                            <dd>
                                @php
                                    $statusMap = [
                                        'verified' => ['badge' => 'success', 'icon' => 'fa-check-circle', 'label' => 'Verified'],
                                        'pending'  => ['badge' => 'warning', 'icon' => 'fa-clock',        'label' => 'Pending'],
                                        'failed'   => ['badge' => 'danger',  'icon' => 'fa-times-circle', 'label' => 'Failed'],
                                    ];
                                    $statusInfo = $statusMap[$emailAccount->status] ?? ['badge' => 'info', 'icon' => 'fa-circle', 'label' => ucfirst($emailAccount->status)];
                                @endphp
                                <span class="badge-{{ $statusInfo['badge'] }} inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
                                    <i class="fas {{ $statusInfo['icon'] }} mr-1 text-[10px]"></i> {{ $statusInfo['label'] }}
                                </span>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4 py-1.5">
                            <dt style="color: var(--text-secondary);">Sync Frequency</dt>
                            <dd class="font-medium" style="color: var(--text-primary);">
                                {{ ucfirst(str_replace('_', ' ', $emailAccount->sync_frequency ?? 'manual')) }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Statistics --}}
            <div class="card">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                        <i class="fas fa-chart-pie mr-2" style="color: var(--secondary);"></i> Statistics
                    </h3>
                    <div class="space-y-2">
                        @php
                            $statRows = [
                                ['icon' => 'fa-inbox',       'label' => 'Inbox',    'value' => $stats['inbox'],  'color' => 'primary'],
                                ['icon' => 'fa-envelope',    'label' => 'Unread',   'value' => $stats['unread'], 'color' => 'danger'],
                                ['icon' => 'fa-paper-plane', 'label' => 'Sent',     'value' => $stats['sent'],   'color' => 'success'],
                                ['icon' => 'fa-pen',         'label' => 'Drafts',   'value' => $stats['drafts'], 'color' => 'warning'],
                                ['icon' => 'fa-trash',       'label' => 'Trash',    'value' => $stats['trash'],  'color' => 'secondary'],
                            ];
                        @endphp
                        @foreach($statRows as $row)
                            <div class="flex items-center justify-between p-2.5 rounded-lg" style="background-color: rgba(var(--{{ $row['color'] }}-rgb), 0.05);">
                                <span class="text-sm flex items-center" style="color: var(--text-secondary);">
                                    <i class="fas {{ $row['icon'] }} mr-2" style="color: var(--{{ $row['color'] }});"></i>
                                    {{ $row['label'] }}
                                </span>
                                <span class="text-sm font-semibold" style="color: var(--{{ $row['color'] }});">{{ $row['value'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    <hr class="my-4" style="border-color: var(--border-color);">

                    <div class="space-y-1.5 text-xs" style="color: var(--text-secondary);">
                        <div class="flex justify-between">
                            <span>Last Sync</span>
                            <span>{{ $emailAccount->last_sync_at?->diffForHumans() ?? 'Never' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Verified At</span>
                            <span>{{ $emailAccount->verified_at?->diffForHumans() ?? 'Never' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Created</span>
                            <span>{{ $emailAccount->created_at?->diffForHumans() ?? '—' }}</span>
                        </div>
                    </div>

                    @if($emailAccount->verification_error)
                        <div class="mt-3 p-2 rounded-lg text-xs" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            {{ $emailAccount->verification_error }}
                        </div>
                    @endif

                    <div class="mt-4 flex flex-col gap-2">
                        <button type="button" onclick="syncEmails({{ $emailAccount->id }})"
                                class="w-full inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm font-medium text-white transition-all hover:scale-105"
                                style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                            <i class="fas fa-sync mr-2"></i> Sync Now
                        </button>
                        <a href="{{ route($routeNamePrefix . '.compose') }}"
                           class="w-full inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm font-medium transition-all hover:scale-105"
                           style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                            <i class="fas fa-plus-circle mr-2"></i> Compose Email
                        </a>
                    </div>
                </div>
            </div>

            {{-- Server Settings (collapsible) --}}
            <div class="card">
                <details>
                    <summary class="p-4 cursor-pointer text-sm font-medium flex items-center justify-between"
                             style="color: var(--text-primary);">
                        <span><i class="fas fa-server mr-2" style="color: var(--secondary);"></i> Server Settings</span>
                        <i class="fas fa-chevron-down text-xs" style="color: var(--text-secondary);"></i>
                    </summary>
                    <div class="px-4 pb-4 grid grid-cols-1 gap-3">
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                            <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">IMAP</p>
                            <p class="text-xs font-mono" style="color: var(--text-primary);">{{ $emailAccount->imap_host }}:{{ $emailAccount->imap_port }}</p>
                            <p class="text-[10px] mt-0.5" style="color: var(--text-secondary);">Encryption: {{ strtoupper($emailAccount->imap_encryption ?: 'NONE') }}</p>
                        </div>
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                            <p class="text-xs font-medium mb-1" style="color: var(--text-secondary);">SMTP</p>
                            <p class="text-xs font-mono" style="color: var(--text-primary);">{{ $emailAccount->smtp_host }}:{{ $emailAccount->smtp_port }}</p>
                            <p class="text-[10px] mt-0.5" style="color: var(--text-secondary);">Encryption: {{ strtoupper($emailAccount->smtp_encryption ?: 'NONE') }}</p>
                        </div>
                    </div>
                </details>
            </div>
        </div>

        {{-- ─── Right pane: messages ───────────────────────────── --}}
        <div class="lg:col-span-8 space-y-6">

            {{-- Mailbox toolbar --}}
            <div class="card">
                <div class="flex flex-wrap items-center justify-between gap-3 p-4 border-b" style="border-color: var(--border-color);">
                    <div class="flex flex-wrap items-center gap-2">
                        @php
                            $folderTabs = [
                                'INBOX'  => ['label' => 'Inbox',  'icon' => 'fa-inbox',       'count' => $stats['inbox']],
                                'SENT'   => ['label' => 'Sent',   'icon' => 'fa-paper-plane', 'count' => $stats['sent']],
                                'DRAFTS' => ['label' => 'Drafts', 'icon' => 'fa-pen',         'count' => $stats['drafts']],
                                'TRASH'  => ['label' => 'Trash',  'icon' => 'fa-trash',       'count' => $stats['trash']],
                            ];
                        @endphp

                        @foreach($folderTabs as $key => $tab)
                            <a href="{{ route($routeNamePrefix . '.show', [$emailAccount->id, 'folder' => $key]) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
                               style="{{ $currentFolder === $key
                                    ? 'background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);'
                                    : 'background-color: rgba(var(--secondary-rgb), 0.05); color: var(--text-secondary); border: 1px solid transparent;' }}">
                                <i class="fas {{ $tab['icon'] }}"></i>
                                {{ $tab['label'] }}
                                <span class="text-[10px] px-1.5 py-0.5 rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.15);">
                                    {{ $tab['count'] }}
                                </span>
                            </a>
                        @endforeach
                    </div>

                    <form method="GET" action="{{ route($routeNamePrefix . '.show', $emailAccount->id) }}"
                          class="flex items-center gap-2">
                        <input type="hidden" name="folder" value="{{ $currentFolder }}">
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs"
                               style="color: var(--text-secondary);"></i>
                            <input type="text" name="q" value="{{ $search }}"
                                   placeholder="Search messages…"
                                   class="pl-8 pr-3 py-1.5 rounded-lg text-xs focus:ring-2 focus:outline-none w-48 sm:w-56"
                                   style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);">
                        </div>
                        <button type="submit"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
                                style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.2);">
                            <i class="fas fa-search"></i>
                        </button>
                        @if($search !== '')
                            <a href="{{ route($routeNamePrefix . '.show', [$emailAccount->id, 'folder' => $currentFolder]) }}"
                               class="px-2 py-1.5 rounded-lg text-xs transition-all"
                               style="color: var(--text-secondary);">
                                <i class="fas fa-times"></i>
                            </a>
                        @endif
                    </form>
                </div>

                {{-- Message list --}}
                <div class="divide-y" style="border-color: var(--border-color);">
                    @forelse($emails as $email)
                        @php
                            $isSelected = $selectedEmail && $selectedEmail->id === $email->id;
                            $preview = \Illuminate\Support\Str::limit(
                                trim(preg_replace('/\s+/', ' ', strip_tags($email->html_body ?? $email->body ?? ''))),
                                140
                            );
                        @endphp

                        <a href="{{ route($routeNamePrefix . '.show', [$emailAccount->id, 'folder' => $currentFolder, 'email' => $email->id]) }}"
                           class="flex items-start gap-3 p-4 transition-all hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $isSelected ? 'ring-2 ring-inset' : '' }}"
                           style="{{ $isSelected ? 'background-color: rgba(var(--primary-rgb), 0.06); ring-color: rgba(var(--primary-rgb), 0.3);' : (!$email->is_read ? 'background-color: rgba(var(--info-rgb), 0.04);' : '') }}">

                            {{-- Unread dot --}}
                            <div class="flex-shrink-0 pt-1.5">
                                @if(!$email->is_read)
                                    <div class="w-2.5 h-2.5 rounded-full" style="background-color: var(--primary);"></div>
                                @else
                                    <div class="w-2.5 h-2.5"></div>
                                @endif
                            </div>

                            {{-- Sender / Subject / Preview --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-semibold truncate" style="color: var(--text-primary);">
                                        {{ $email->from_name ?: $email->from_email ?: 'Unknown Sender' }}
                                    </p>
                                    <span class="text-xs flex-shrink-0" style="color: var(--text-secondary);">
                                        {{ formatEmailDate($email->received_at ?? $email->created_at) }}
                                    </span>
                                </div>

                                <div class="flex items-center gap-2 mt-0.5">
                                    <p class="text-sm truncate" style="color: var(--text-primary);">
                                        {{ $email->subject ?: '(No Subject)' }}
                                    </p>
                                    @if(($email->attachment_count ?? 0) > 0)
                                        <i class="fas fa-paperclip text-xs flex-shrink-0" style="color: var(--text-secondary);"
                                           title="{{ $email->attachment_count }} attachment(s)"></i>
                                    @endif
                                </div>

                                @if($preview !== '')
                                    <p class="text-xs mt-0.5 truncate" style="color: var(--text-secondary);">
                                        {{ $preview }}
                                    </p>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="text-center py-12">
                            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3"
                                 style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                <i class="fas fa-inbox text-xl" style="color: var(--secondary);"></i>
                            </div>
                            <h4 class="text-sm font-semibold mb-1" style="color: var(--text-primary);">
                                @if($search !== '')
                                    No messages match "{{ $search }}"
                                @else
                                    No messages in {{ strtolower($folderTabs[$currentFolder]['label'] ?? $currentFolder) }}
                                @endif
                            </h4>
                            <p class="text-xs" style="color: var(--text-secondary);">
                                Click <strong>Sync Now</strong> to fetch the latest messages.
                            </p>
                        </div>
                    @endforelse
                </div>

                {{-- Pagination --}}
                @if(method_exists($emails, 'links') && $emails->hasPages())
                    <div class="p-4 border-t" style="border-color: var(--border-color);">
                        {{ $emails->links() }}
                    </div>
                @endif
            </div>

            {{-- ─── Selected email reader ──────────────────────── --}}
            @if($selectedEmail)
                @php
                    // If the body hasn't been fetched yet, trigger lazy load
                    $needsFetch = empty($selectedEmail->html_body) && empty($selectedEmail->body);
                @endphp

                <div class="card" id="email-reader">
                    {{-- Reader header --}}
                    <div class="p-4 border-b flex items-start justify-between gap-3" style="border-color: var(--border-color);">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold truncate" style="color: var(--text-primary);">
                                {{ $selectedEmail->subject ?: '(No Subject)' }}
                            </h3>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                <span class="font-medium" style="color: var(--text-primary);">
                                    {{ $selectedEmail->from_name ?: $selectedEmail->from_email }}
                                </span>
                                &lt;{{ $selectedEmail->from_email }}&gt;
                                <span class="mx-1">•</span>
                                {{ $selectedEmail->received_at?->format('M j, Y g:i A') ?? '—' }}
                            </p>
                            @if(!empty($selectedEmail->to_email))
                                <p class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                    To: {{ $selectedEmail->to_email }}
                                </p>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 flex-shrink-0">
                            @if(($selectedEmail->attachment_count ?? 0) > 0)
                                <span class="text-xs px-2 py-0.5 rounded-full"
                                      style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    <i class="fas fa-paperclip mr-1"></i> {{ $selectedEmail->attachment_count }}
                                </span>
                            @endif

                            <button type="button"
                                    onclick="toggleRead({{ $selectedEmail->id }}, {{ $selectedEmail->is_read ? 'false' : 'true' }})"
                                    class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
                                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.2);">
                                @if($selectedEmail->is_read)
                                    <i class="fas fa-envelope mr-1"></i> Mark Unread
                                @else
                                    <i class="fas fa-envelope-open mr-1"></i> Mark Read
                                @endif
                            </button>

                            <button type="button"
                                    onclick="deleteEmail({{ $selectedEmail->id }})"
                                    class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                                <i class="fas fa-trash mr-1"></i> Delete
                            </button>
                        </div>
                    </div>

                    {{-- Reader body --}}
                    <div class="p-6" id="email-body-container"
                         data-email-id="{{ $selectedEmail->id }}"
                         data-needs-fetch="{{ $needsFetch ? '1' : '0' }}">

                        @if($needsFetch)
                            {{-- Lazy-load placeholder --}}
                            <div id="email-body-loading" class="text-center py-10">
                                <i class="fas fa-spinner fa-spin text-2xl" style="color: var(--primary);"></i>
                                <p class="text-xs mt-3" style="color: var(--text-secondary);">
                                    Loading message body…
                                </p>
                            </div>
                            <div id="email-body-content" class="hidden prose prose-sm max-w-none dark:prose-invert"
                                 style="color: var(--text-primary);">
                            </div>
                        @else
                            {{-- Render stored body --}}
                            @if($selectedEmail->html_body)
                                <div class="prose prose-sm max-w-none dark:prose-invert"
                                     style="color: var(--text-primary); word-break: break-word;">
                                    {!! $selectedEmail->html_body !!}
                                </div>
                            @elseif($selectedEmail->body)
                                <pre class="whitespace-pre-wrap text-sm font-sans"
                                     style="color: var(--text-primary);">{{ $selectedEmail->body }}</pre>
                            @else
                                <p class="text-sm italic" style="color: var(--text-secondary);">
                                    (No content available)
                                </p>
                            @endif
                        @endif
                    </div>
                </div>
            @else
                {{-- Empty reader state --}}
                <div class="card">
                    <div class="text-center py-16">
                        <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3"
                             style="background-color: rgba(var(--secondary-rgb), 0.1);">
                            <i class="fas fa-envelope-open-text text-xl" style="color: var(--secondary);"></i>
                        </div>
                        <h4 class="text-sm font-semibold mb-1" style="color: var(--text-primary);">
                            Select a message to read
                        </h4>
                        <p class="text-xs" style="color: var(--text-secondary);">
                            Click any message in the list above to view its full content.
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
    @endif
</div>

{{-- ═════════════════════════════════════════════════════════ --}}
{{-- SYNC OVERLAY                                             --}}
{{-- ═════════════════════════════════════════════════════════ --}}
<div id="syncOverlay" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="rounded-lg shadow-xl p-8 text-center" style="background-color: var(--card-bg);">
            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"
                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                <i class="fas fa-sync fa-spin text-2xl text-white"></i>
            </div>
            <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Syncing Emails</h4>
            <p class="text-sm" style="color: var(--text-secondary);">Please wait while we sync your emails…</p>
            <div class="mt-4 w-full max-w-xs mx-auto h-1.5 rounded-full overflow-hidden" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                <div class="h-full rounded-full animate-pulse" style="width: 100%; background: linear-gradient(90deg, var(--primary), var(--secondary));"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const baseUrl    = '/{{ $urlPath }}';
    const routePrefix = '{{ $routeNamePrefix }}';
    const accountId  = {{ $emailAccount ? $emailAccount->id : 'null' }};
    const csrfToken  = '{{ csrf_token() }}';

    // ========================================== //
    // 🔌 SHARED FETCH HELPER                     //
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
            const clean = text.replace(/^\uFEFF/, '');
            data = clean ? JSON.parse(clean) : {};
        } catch (e) {
            console.error('Non-JSON response:', text.slice(0, 300));
            throw new Error(`Server returned non-JSON response (HTTP ${response.status})`);
        }

        if (!response.ok) {
            throw new Error(data.message || `HTTP ${response.status}`);
        }

        return data;
    }

    // ========================================== //
    // ⭐ SET PRIMARY                              //
    // ========================================== //
    function setPrimary(id) {
        if (!confirm('Set this as your primary email account?')) return;

        apiFetch(`${baseUrl}/${id}/set-primary`, { method: 'POST' })
            .then(data => {
                showNotification(data.message || 'Primary account updated', 'success');
                setTimeout(() => window.location.reload(), 1200);
            })
            .catch(err => showNotification('Failed to set primary: ' + err.message, 'error'));
    }

    // ========================================== //
    // 🔄 SYNC EMAILS                             //
    // ========================================== //
    function syncEmails(id) {
        if (!id) return;

        const overlay = document.getElementById('syncOverlay');
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        apiFetch(`${baseUrl}/${id}/sync`, { method: 'POST' })
            .then(data => {
                overlay.classList.add('hidden');
                document.body.style.overflow = 'auto';
                showNotification(data.message || 'Emails synced successfully!', data.success ? 'success' : 'error');
                if (data.success) {
                    setTimeout(() => window.location.reload(), 1500);
                }
            })
            .catch(err => {
                overlay.classList.add('hidden');
                document.body.style.overflow = 'auto';
                showNotification('Sync failed: ' + err.message, 'error');
            });
    }

    // ========================================== //
    // 👁️ TOGGLE READ                            //
    // ========================================== //
    function toggleRead(emailId, markAsRead) {
        const endpoint = markAsRead ? 'mark-read' : 'mark-unread';

        apiFetch(`${baseUrl}/${accountId}/emails/${emailId}/${endpoint}`, { method: 'POST' })
            .then(data => {
                showNotification(markAsRead ? 'Marked as read' : 'Marked as unread', 'success');
                setTimeout(() => window.location.reload(), 800);
            })
            .catch(err => showNotification('Failed: ' + err.message, 'error'));
    }

    // ========================================== //
    // 🗑️ DELETE EMAIL                            //
    // ========================================== //
    function deleteEmail(emailId) {
        if (!confirm('Delete this email? This cannot be undone.')) return;

        apiFetch(`${baseUrl}/${accountId}/emails/${emailId}`, { method: 'DELETE' })
            .then(data => {
                showNotification('Email deleted', 'success');
                // Redirect back to the list so the reader pane doesn't point at a dead row
                const back = `{{ route($routeNamePrefix . '.show', $emailAccount?->id ?? 0) }}`;
                setTimeout(() => window.location.href = back, 800);
            })
            .catch(err => showNotification('Delete failed: ' + err.message, 'error'));
    }

    // ========================================== //
    // 📥 LAZY BODY FETCH (auto-trigger)         //
    // ========================================== //
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.getElementById('email-body-container');
        if (!container) return;

        const needsFetch = container.dataset.needsFetch === '1';
        const emailId    = container.dataset.emailId;

        if (!needsFetch || !emailId) return;

        // Hit the viewEmail route — it lazy-fetches the body server-side
        // and renders the HTML. We just replace the inner HTML with the rendered pane.
        apiFetch(`{{ route($routeNamePrefix . '.view-email', [$emailAccount?->id ?? 0, '__ID__']) }}`.replace('__ID__', emailId))
            .then(() => {
                // Some controllers return JSON; if so, we simply reload.
                window.location.reload();
            })
            .catch(() => {
                // Fallback — reload to trigger server-side lazy fetch
                window.location.reload();
            });
    });

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

        const n = document.createElement('div');
        n.className = 'custom-notification fixed top-4 right-4 z-[9999] px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 text-white';
        n.style.backgroundColor = color.bg;
        n.style.animation = 'slideInRight 0.3s ease-out';
        n.style.minWidth = '300px';
        n.style.maxWidth = '500px';
        n.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${color.icon} mr-2 text-lg"></i>
                <span class="text-sm">${escapeHtml(message)}</span>
                <button onclick="this.closest('.custom-notification').remove()" class="ml-3 text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        document.body.appendChild(n);

        setTimeout(() => {
            n.style.opacity = '0';
            n.style.transform = 'translateX(100%)';
            setTimeout(() => n.remove(), 300);
        }, 5000);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ========================================== //
    // ⌨️ KEYBOARD                                //
    // ========================================== //
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.custom-notification').forEach(n => n.remove());
        }
        if (e.key === 'r' && !e.ctrlKey && !e.metaKey && !e.target.closest('input, textarea, select')) {
            e.preventDefault();
            if (accountId) syncEmails(accountId);
        }
    });

    // ========================================== //
    // 🎨 STYLES                                  //
    // ========================================== //
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to   { transform: translateX(0);    opacity: 1; }
        }

        .badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); }
        .badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); }
        .badge-danger  { background-color: rgba(var(--danger-rgb), 0.1);  color: var(--danger);  }
        .badge-info    { background-color: rgba(var(--info-rgb), 0.1);    color: var(--info);    }

        /* Reader pane — keep long lines, quotes, and pre-blocks readable */
        #email-body-container img { max-width: 100%; height: auto; }
        #email-body-container table { width: 100% !important; }
        #email-body-container a { color: var(--primary); text-decoration: underline; }
        #email-body-container blockquote {
            border-left: 3px solid var(--border-color);
            padding-left: 1rem;
            margin-left: 0;
            color: var(--text-secondary);
        }
        #email-body-container pre {
            background-color: rgba(var(--secondary-rgb), 0.06);
            padding: 0.75rem;
            border-radius: 6px;
            overflow-x: auto;
        }

        /* Native <details> chevron rotation */
        details[open] > summary .fa-chevron-down { transform: rotate(180deg); transition: transform 0.2s; }
        details > summary { list-style: none; }
        details > summary::-webkit-details-marker { display: none; }
    `;
    document.head.appendChild(style);

    console.log('✅ Email Account Show Loaded');
    console.log('Account ID:', accountId);
</script>
@endsection