{{-- email-accounts/index.blade.php --}}
@php
    use App\Models\UserEmailAccount;

    $user = auth()->user();

    // ────────────────────────────────────────────
    // ROLE DETECTION
    // ────────────────────────────────────────────
    $isSuperAdmin = $user->isSuperAdmin();
    $isAdmin      = $user->isAdmin();
    $isDeveloper  = $user->isDeveloper();
    $isLandlord   = $user->isLandlord();
    $isTenant     = $user->isTenant();

    // ────────────────────────────────────────────
    // ✅ Admin is READ-ONLY (matches controller)
    // ────────────────────────────────────────────
    $isReadOnly = $isAdmin;
    $canCreate  = !$isReadOnly;

    // ────────────────────────────────────────────
    // ROLE → ROUTE PREFIX / LAYOUT / BACK LINK
    // ────────────────────────────────────────────
    if ($isSuperAdmin) {
        $routeNamePrefix = 'super-admin.email-accounts';
        $urlPath         = 'super-admin/email-accounts';
        $layout          = 'layouts.app';
        $pageTitle       = 'Email Accounts - Super Admin';
        $backRoute       = route('super-admin.dashboard');
        $backIcon        = 'fa-dashboard';
        $backText        = 'Dashboard';
    } elseif ($isDeveloper) {
        $routeNamePrefix = 'developer.email-accounts';
        $urlPath         = 'developer/email-accounts';
        $layout          = 'layouts.dev';
        $pageTitle       = 'Email Accounts - Developer';
        $backRoute       = route('developer.dashboard');
        $backIcon        = 'fa-dashboard';
        $backText        = 'Dashboard';
    } elseif ($isAdmin) {
        $routeNamePrefix = 'admin.email-accounts';
        $urlPath         = 'admin/email-accounts';
        $layout          = 'layouts.app';
        $pageTitle       = 'Email Accounts - Admin';
        $backRoute       = route('admin.dashboard');
        $backIcon        = 'fa-dashboard';
        $backText        = 'Dashboard';
    } elseif ($isLandlord) {
        $routeNamePrefix = 'landlord.email-accounts';
        $urlPath         = 'landlord/email-accounts';
        $layout          = 'layouts.landlord';
        $pageTitle       = 'My Email Accounts';
        $backRoute       = route('landlord.dashboard');
        $backIcon        = 'fa-dashboard';
        $backText        = 'Dashboard';
    } elseif ($isTenant) {
        $routeNamePrefix = 'tenant.email-accounts';
        $urlPath         = 'tenant/email-accounts';
        $layout          = 'layouts.tenant';
        $pageTitle       = 'My Email Accounts';
        $backRoute       = route('tenant.dashboard');
        $backIcon        = 'fa-dashboard';
        $backText        = 'Dashboard';
    } else {
        $routeNamePrefix = 'email-accounts';
        $urlPath         = 'email-accounts';
        $layout          = 'layouts.app';
        $pageTitle       = 'Email Accounts';
        $backRoute       = route('dashboard');
        $backIcon        = 'fa-dashboard';
        $backText        = 'Dashboard';
    }

    $emailAccounts  = $emailAccounts  ?? collect();
    $primaryAccount = $primaryAccount ?? null;

    $successMessage = session('success');
    $errorMessage   = session('error');
    $warningMessage = session('warning');

    // Single-query unread count (no N+1)
    $accountIds = $emailAccounts->pluck('id')->all();

    $totalUnread = empty($accountIds)
        ? 0
        : \App\Models\Email::whereIn('user_email_account_id', $accountIds)
            ->where('is_read', false)
            ->where('folder', 'INBOX')
            ->count();

    $verifiedCount = $emailAccounts->where('status', 'verified')->count();
    $pendingCount  = $emailAccounts->where('status', 'pending')->count();
    $failedCount   = $emailAccounts->where('status', 'failed')->count();
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
                        <i class="fas fa-envelope text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-envelope mr-2" style="color: var(--primary);"></i>
                        {{ $pageTitle }}
                    </h2>
                    <div class="text-sm flex flex-wrap items-center mt-1 gap-2" style="color: var(--text-secondary);">
                        <span>
                            <i class="fas fa-info-circle mr-1"></i>
                            {{ $isReadOnly ? 'View all linked email accounts (read-only)' : 'Manage your linked email accounts' }}
                        </span>
                        <span class="hidden sm:inline">•</span>
                        <span><i class="fas fa-envelope mr-1"></i> {{ $emailAccounts->count() }} accounts linked</span>
                        @if($totalUnread > 0)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444;">
                                <i class="fas fa-circle text-[6px] mr-1"></i>
                                {{ $totalUnread }} unread
                            </span>
                        @endif
                        @if($primaryAccount)
                            <span class="hidden sm:inline">•</span>
                            <span>
                                <i class="fas fa-star mr-1 text-yellow-500"></i>
                                <span class="text-yellow-600 font-medium truncate max-w-[150px] inline-block">{{ $primaryAccount->email }}</span>
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

    {{-- Admin read-only banner --}}
    @if($isReadOnly)
    <div class="px-4 py-3 rounded relative mb-4"
         style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.3); color: var(--info);">
        <div class="flex items-center">
            <i class="fas fa-info-circle mr-2"></i>
            <span class="text-sm">
                <strong>Read-only view.</strong> As an Administrator you can view, send, and sync from any account, but linking, editing, and deleting must be done by a Super Admin.
            </span>
        </div>
    </div>
    @endif

    <!-- Success/Error Messages -->
    @if($successMessage)
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 dark:bg-green-900 dark:border-green-700 dark:text-green-300" role="alert">
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
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 dark:bg-red-900 dark:border-red-700 dark:text-red-300" role="alert">
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
    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4 dark:bg-yellow-900 dark:border-yellow-700 dark:text-yellow-300" role="alert">
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

    <!-- Actions Card -->
    <div class="card">
        <div class="p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    @if($canCreate)
                    <a href="{{ route($routeNamePrefix . '.create') }}"
                       class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium text-white transition-all hover:scale-105"
                       style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                        <i class="fas fa-plus-circle mr-2"></i> Link New Email
                    </a>
                    @endif

                    @if($primaryAccount)
                    <button onclick="syncEmails({{ $primaryAccount->id }})"
                            class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all hover:scale-105"
                            style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                        <i class="fas fa-sync mr-2"></i> Sync Primary
                    </button>
                    @endif

                    @if($emailAccounts->count() > 0)
                    <button onclick="syncAllEmails()"
                            class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all hover:scale-105"
                            style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <i class="fas fa-sync-alt mr-2"></i> Sync All
                    </button>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if($emailAccounts->count() > 0)
                    <span class="text-xs px-3 py-1 rounded-full"
                          style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-check-circle mr-1"></i> {{ $verifiedCount }} Verified
                    </span>
                    <span class="text-xs px-3 py-1 rounded-full"
                          style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-clock mr-1"></i> {{ $pendingCount }} Pending
                    </span>
                    @if($failedCount > 0)
                    <span class="text-xs px-3 py-1 rounded-full"
                          style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-exclamation-circle mr-1"></i> {{ $failedCount }} Failed
                    </span>
                    @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Email Accounts List -->
    <div class="grid grid-cols-1 gap-6">
        @if($emailAccounts->isEmpty())
            <!-- Empty State -->
            <div class="card">
                <div class="text-center py-12">
                    <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4"
                         style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-envelope text-2xl" style="color: var(--primary);"></i>
                    </div>
                    <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                        No Email Accounts Linked
                    </h4>
                    <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                        @if($isReadOnly)
                            No email accounts have been linked yet. Please contact a Super Admin to link an account.
                        @else
                            Link your email account to send and receive messages directly from your dashboard.
                            You can link Gmail, Outlook, Yahoo, or any custom email provider.
                        @endif
                    </p>
                    @if($canCreate)
                    <a href="{{ route($routeNamePrefix . '.create') }}"
                       class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-white transition-all hover:scale-105"
                       style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                        <i class="fas fa-link mr-2"></i> Link Your First Email
                    </a>
                    @endif
                </div>
            </div>
        @else
            <!-- Account Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                @foreach($emailAccounts as $account)
                <div class="card hover:shadow-lg transition-all duration-300 {{ $account->is_primary ? 'border-2' : '' }}"
                     style="{{ $account->is_primary ? 'border-color: var(--primary);' : '' }}"
                     data-account-id="{{ $account->id }}">
                    <div class="p-6">
                        <!-- Header -->
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center min-w-0 flex-1">
                                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3 flex-shrink-0 text-lg font-semibold"
                                     style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                                    {{ strtoupper(substr($account->email, 0, 2)) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h4 class="font-semibold truncate" style="color: var(--text-primary);">
                                        {{ $account->display_name ?? $account->email }}
                                        @if($account->is_primary)
                                        <span class="ml-1 text-xs px-1.5 py-0.5 rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 inline-block">
                                            <i class="fas fa-star mr-0.5"></i>
                                        </span>
                                        @endif
                                    </h4>
                                    <p class="text-sm truncate" style="color: var(--text-secondary);">{{ $account->email }}</p>
                                </div>
                            </div>
                            <div class="flex flex-col items-end flex-shrink-0 ml-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $account->status === 'verified' ? 'badge-success' : ($account->status === 'pending' ? 'badge-warning' : 'badge-danger') }}">
                                    @if($account->status === 'verified')
                                        <i class="fas fa-check-circle mr-0.5 text-[10px]"></i> Verified
                                    @elseif($account->status === 'pending')
                                        <i class="fas fa-clock mr-0.5 text-[10px]"></i> Pending
                                    @elseif($account->status === 'failed')
                                        <i class="fas fa-exclamation-circle mr-0.5 text-[10px]"></i> Failed
                                    @else
                                        <i class="fas fa-circle mr-0.5 text-[10px]"></i> {{ ucfirst($account->status) }}
                                    @endif
                                </span>
                                @if($account->provider !== 'custom')
                                <span class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                    <i class="fas fa-cloud mr-0.5"></i> {{ ucfirst($account->provider) }}
                                </span>
                                @endif
                            </div>
                        </div>

                        <!-- Details -->
                        <div class="space-y-1.5 mb-4">
                            <div class="flex items-center text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-server w-5 text-gray-400 text-xs"></i>
                                <span class="truncate">{{ $account->imap_host }}</span>
                                <span class="ml-1 text-xs" style="color: var(--text-secondary);">(IMAP)</span>
                            </div>
                            <div class="flex items-center text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-paper-plane w-5 text-gray-400 text-xs"></i>
                                <span class="truncate">{{ $account->smtp_host }}</span>
                                <span class="ml-1 text-xs" style="color: var(--text-secondary);">(SMTP)</span>
                            </div>
                            <div class="flex items-center text-sm" style="color: var(--text-primary);">
                                <i class="fas fa-clock w-5 text-gray-400 text-xs"></i>
                                <span>{{ ucfirst(str_replace('_', ' ', $account->sync_frequency)) }}</span>
                            </div>
                            @if($account->last_sync_at)
                            <div class="flex items-center text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-sync w-5 text-gray-400 text-xs"></i>
                                <span>Last sync: {{ $account->last_sync_at->diffForHumans() }}</span>
                            </div>
                            @endif
                            <div class="flex items-center text-sm">
                                @if($account->is_connected)
                                    <i class="fas fa-circle w-5 text-green-500 text-xs"></i>
                                    <span class="text-green-600 dark:text-green-400">Connected</span>
                                @else
                                    <i class="fas fa-circle w-5 text-red-500 text-xs"></i>
                                    <span class="text-red-600 dark:text-red-400">Disconnected</span>
                                    @if($account->last_connection_error)
                                    <span class="ml-1 text-xs cursor-help" title="{{ $account->last_connection_error }}">
                                        <i class="fas fa-info-circle text-gray-400"></i>
                                    </span>
                                    @endif
                                @endif
                            </div>
                            @if($account->verification_error && $account->status !== 'verified')
                            <div class="flex items-center text-sm text-red-500">
                                <i class="fas fa-exclamation-triangle w-5 text-red-400 text-xs"></i>
                                <span class="text-xs truncate" title="{{ $account->verification_error }}">{{ Str::limit($account->verification_error, 40) }}</span>
                            </div>
                            @endif
                        </div>

                        <!-- Quota -->
                        <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                            <div class="flex justify-between text-xs mb-0.5" style="color: var(--text-secondary);">
                                <span>Send Quota</span>
                                <span>{{ $account->emails_sent_today ?? 0 }} / {{ $account->daily_send_limit ?? 500 }}</span>
                            </div>
                            <div class="w-full h-1 rounded-full overflow-hidden" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                                <div class="h-full rounded-full transition-all"
                                     style="width: {{ min(100, (($account->emails_sent_today ?? 0) / max(1, ($account->daily_send_limit ?? 500))) * 100) }}%;
                                            background: linear-gradient(90deg, var(--primary), var(--secondary));">
                                </div>
                            </div>
                            <div class="flex justify-between text-xs mt-1 mb-0.5" style="color: var(--text-secondary);">
                                <span>Receive Quota</span>
                                <span>{{ $account->emails_received_today ?? 0 }} / {{ $account->daily_receive_limit ?? 1000 }}</span>
                            </div>
                            <div class="w-full h-1 rounded-full overflow-hidden" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                                <div class="h-full rounded-full transition-all"
                                     style="width: {{ min(100, (($account->emails_received_today ?? 0) / max(1, ($account->daily_receive_limit ?? 1000))) * 100) }}%;
                                            background: linear-gradient(90deg, var(--info), var(--primary));">
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-between pt-3 border-t" style="border-color: var(--border-color);">
                            <div class="flex gap-1">
                                {{-- Set Primary (admins can't) --}}
                                @if(!$isReadOnly && !$account->is_primary && $account->status === 'verified')
                                <button onclick="setPrimary({{ $account->id }})"
                                        class="action-btn primary" title="Set as Primary">
                                    <i class="fas fa-star"></i>
                                </button>
                                @endif

                                {{-- Sync (everyone can) --}}
                                <button onclick="syncEmails({{ $account->id }})"
                                        class="action-btn sync" title="Sync Now">
                                    <i class="fas fa-sync"></i>
                                </button>

                                {{-- Re-verify (only non-admins) --}}
                                @if(!$isReadOnly && ($account->status === 'pending' || $account->status === 'failed'))
                                <button onclick="reverifyAccount({{ $account->id }})"
                                        class="action-btn reverify" title="Re-verify Account">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                                @endif

                                {{-- Edit (only non-admins) --}}
                                @if(!$isReadOnly)
                                <a href="{{ route($routeNamePrefix . '.edit', $account->id) }}"
                                   class="action-btn edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @endif
                            </div>

                            {{-- Delete (only non-admins) --}}
                            @if(!$isReadOnly)
                            <button onclick="deleteAccount({{ $account->id }}, '{{ addslashes($account->email) }}')"
                                    class="action-btn delete" title="Unlink" id="delete-btn-{{ $account->id }}">
                                <i class="fas fa-unlink"></i>
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70" onclick="hideDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="rounded-lg shadow-xl w-full max-w-md" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center p-5 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-unlink mr-2" style="color: var(--danger);"></i> Unlink Email Account
                </h3>
                <button type="button" onclick="hideDeleteModal()" class="transition" style="color: var(--text-secondary);">
                    <i class="fas fa-times hover:text-red-500"></i>
                </button>
            </div>
            <div class="p-5">
                <div class="mb-4">
                    <p class="text-sm" id="deleteMessage" style="color: var(--text-secondary);"></p>
                </div>

                <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                    <p class="font-medium text-sm" style="color: var(--warning);">⚠️ Note:</p>
                    <ul class="text-xs mt-1 space-y-1" style="color: var(--warning);">
                        <li>• This will unlink the email account from your dashboard</li>
                        <li>• You can re-link it anytime</li>
                        <li>• No emails will be deleted from your email provider</li>
                    </ul>
                </div>
            </div>
            <div class="flex justify-end gap-3 p-5 border-t" style="border-color: var(--border-color);">
                <button type="button" onclick="hideDeleteModal()"
                        class="px-4 py-2 rounded-lg font-medium transition"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                    Cancel
                </button>
                <button type="button" id="confirmDeleteBtn"
                        class="px-4 py-2 rounded-lg font-medium text-white transition hover:opacity-90"
                        style="background-color: var(--danger);">
                    <i class="fas fa-unlink mr-2"></i> Unlink Account
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Sync Loading Overlay -->
<div id="syncOverlay" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="rounded-lg shadow-xl p-8 text-center" style="background-color: var(--card-bg);">
            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"
                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                <i class="fas fa-sync fa-spin text-2xl text-white"></i>
            </div>
            <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);" id="syncOverlayTitle">Processing</h4>
            <p class="text-sm" id="syncOverlayMessage" style="color: var(--text-secondary);">Please wait…</p>
            <div class="mt-4 w-full max-w-xs mx-auto h-1.5 rounded-full overflow-hidden" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                <div class="h-full rounded-full animate-pulse" style="width: 100%; background: linear-gradient(90deg, var(--primary), var(--secondary));"></div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
const baseUrl     = '/{{ $urlPath }}';
const routePrefix = '{{ $routeNamePrefix }}';
const csrfToken   = '{{ csrf_token() }}';
const isReadOnly  = {{ $isReadOnly ? 'true' : 'false' }};

let deleteAccountId    = null;
let deleteAccountEmail = null;

// ========================================== //
// 🛠️ SHARED API FETCH HELPER                 //
// ========================================== //
async function apiFetch(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            ...(options.headers || {}),
        },
    });

    const text = await response.text();

    let data;
    try {
        data = text ? JSON.parse(text) : {};
    } catch (e) {
        console.error('Non-JSON response from', url, ':', text.slice(0, 300));
        throw new Error(`Server returned non-JSON response (HTTP ${response.status})`);
    }

    if (!response.ok) {
        throw new Error(data.message || `HTTP ${response.status}`);
    }

    return data;
}

// ========================================== //
// 📋 SET PRIMARY ACCOUNT                      //
// ========================================== //
function setPrimary(accountId) {
    if (isReadOnly) {
        showNotification('Administrators cannot set primary accounts.', 'error');
        return;
    }
    if (!confirm('Set this as your primary email account?')) return;

    apiFetch(`${baseUrl}/${accountId}/set-primary`, { method: 'POST' })
        .then(data => {
            if (data.success) {
                showNotification('Primary email account updated!', 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showNotification(data.message || 'Failed to set primary account', 'error');
            }
        })
        .catch(error => {
            showNotification('Failed: ' + error.message, 'error');
            console.error('Set primary error:', error);
        });
}

// ========================================== //
// 📨 SYNC EMAILS                              //
// ========================================== //
function syncEmails(accountId) {
    showOverlay('Syncing Emails', 'Please wait while we sync your emails…');

    apiFetch(`${baseUrl}/${accountId}/sync`, { method: 'POST' })
        .then(data => {
            hideOverlay();
            if (data.success) {
                showNotification(data.message || 'Emails synced successfully!', 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showNotification(data.message || 'Sync failed', 'error');
            }
        })
        .catch(error => {
            hideOverlay();
            showNotification('Sync failed: ' + error.message, 'error');
            console.error('Sync error:', error);
        });
}

async function syncAllEmails() {
    if (!confirm('Sync all your email accounts?')) return;

    const nodes = Array.from(document.querySelectorAll('[data-account-id]'));
    const ids = nodes.map(el => el.dataset.accountId).filter(Boolean);

    if (ids.length === 0) {
        showNotification('No accounts to sync', 'info');
        return;
    }

    showOverlay('Syncing All', `Syncing ${ids.length} account(s)…`);

    const results = await Promise.allSettled(ids.map(id =>
        apiFetch(`${baseUrl}/${id}/sync`, { method: 'POST' })
    ));

    hideOverlay();

    const succeeded = results.filter(r => r.status === 'fulfilled' && r.value?.success).length;
    const failed    = results.length - succeeded;

    if (failed === 0) {
        showNotification(`All ${succeeded} account(s) synced!`, 'success');
    } else if (succeeded === 0) {
        showNotification(`Sync failed for all ${failed} account(s)`, 'error');
    } else {
        showNotification(`Synced ${succeeded}/${results.length} account(s)`, 'warning');
    }

    setTimeout(() => window.location.reload(), 1800);
}

// ========================================== //
// 🔄 RE-VERIFY ACCOUNT                        //
// ========================================== //
function reverifyAccount(accountId) {
    if (isReadOnly) {
        showNotification('Administrators cannot re-verify accounts.', 'error');
        return;
    }
    if (!confirm('Re-verify this email account? This will test your credentials again.')) {
        return;
    }

    showOverlay('Re-verifying', 'Testing your IMAP and SMTP credentials…');

    apiFetch(`${baseUrl}/${accountId}/reverify`, { method: 'POST' })
        .then(data => {
            hideOverlay();
            if (data.success) {
                showNotification(data.message || 'Account re-verified successfully!', 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showNotification(data.message || 'Re-verification failed. Please check your credentials.', 'error');
            }
        })
        .catch(error => {
            hideOverlay();
            showNotification('Re-verification failed: ' + error.message, 'error');
            console.error('Reverify error:', error);
        });
}

// ========================================== //
// 🗑️ DELETE ACCOUNT                           //
// ========================================== //
function deleteAccount(accountId, email) {
    if (isReadOnly) {
        showNotification('Administrators cannot unlink accounts.', 'error');
        return;
    }

    deleteAccountId    = accountId;
    deleteAccountEmail = email;

    const modal   = document.getElementById('deleteModal');
    const message = document.getElementById('deleteMessage');

    message.innerHTML = `Are you sure you want to unlink <strong>${escapeHtml(email)}</strong>?`;

    const confirmBtn = document.getElementById('confirmDeleteBtn');
    confirmBtn.onclick = function () {
        executeDelete(accountId);
    };

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function executeDelete(accountId) {
    hideDeleteModal();

    const btn = document.getElementById(`delete-btn-${accountId}`);
    if (btn) {
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled  = true;
        btn.style.opacity = '0.7';
    }

    apiFetch(`${baseUrl}/${accountId}`, { method: 'DELETE' })
        .then(data => {
            if (data.success) {
                showNotification('Email account unlinked successfully!', 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showNotification(data.message || 'Failed to unlink account', 'error');
                if (btn) {
                    btn.innerHTML     = '<i class="fas fa-unlink"></i>';
                    btn.disabled      = false;
                    btn.style.opacity = '1';
                }
            }
        })
        .catch(error => {
            console.error('Delete error:', error);
            showNotification('Delete failed: ' + error.message, 'error');
            if (btn) {
                btn.innerHTML     = '<i class="fas fa-unlink"></i>';
                btn.disabled      = false;
                btn.style.opacity = '1';
            }
        });
}

function hideDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// ========================================== //
// 🌀 OVERLAY HELPERS                          //
// ========================================== //
function showOverlay(title, message) {
    const overlay = document.getElementById('syncOverlay');
    if (!overlay) return;
    const titleEl = document.getElementById('syncOverlayTitle');
    const msgEl   = document.getElementById('syncOverlayMessage');
    if (titleEl) titleEl.textContent = title;
    if (msgEl)   msgEl.textContent   = message;
    overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideOverlay() {
    const overlay = document.getElementById('syncOverlay');
    if (overlay) overlay.classList.add('hidden');
    document.body.style.overflow = 'auto';
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
    notification.style.boxShadow = '0 10px 25px rgba(0,0,0,0.2)';
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
    }, 6000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ========================================== //
// ⌨️ KEYBOARD SHORTCUTS                      //
// ========================================== //
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        hideDeleteModal();
    }
});

// ========================================== //
// 🎨 CSS STYLES                              //
// ========================================== //
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to   { transform: translateX(0);    opacity: 1; }
    }

    .action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        transition: all 0.2s ease;
        cursor: pointer;
        border: none;
        font-size: 14px;
    }

    .action-btn.primary  { background-color: rgba(251, 191, 36, 0.1); color: #f59e0b; }
    .action-btn.primary:hover { background-color: rgba(251, 191, 36, 0.2); transform: translateY(-2px); }

    .action-btn.sync     { background-color: rgba(var(--info-rgb), 0.1);    color: var(--info); }
    .action-btn.sync:hover { background-color: rgba(var(--info-rgb), 0.2); transform: translateY(-2px); }

    .action-btn.reverify { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); }
    .action-btn.reverify:hover { background-color: rgba(var(--warning-rgb), 0.2); transform: translateY(-2px); }

    .action-btn.edit     { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); }
    .action-btn.edit:hover { background-color: rgba(var(--warning-rgb), 0.2); transform: translateY(-2px); }

    .action-btn.delete   { background-color: rgba(var(--danger-rgb), 0.1);  color: var(--danger); }
    .action-btn.delete:hover { background-color: rgba(var(--danger-rgb), 0.2); transform: translateY(-2px); }

    .badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); }
    .badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); }
    .badge-danger  { background-color: rgba(var(--danger-rgb), 0.1);  color: var(--danger); }

    #deleteModal { animation: fadeIn 0.2s ease-out; }

    @keyframes fadeIn {
        from { opacity: 0; }
        to   { opacity: 1; }
    }

    @media (prefers-color-scheme: dark) {
        .modal-content { box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
    }
`;
document.head.appendChild(style);

console.log('✅ Email Accounts Index Loaded');
console.log('Base URL:', baseUrl);
console.log('Route Prefix:', routePrefix);
console.log('Read-only mode:', isReadOnly);
</script>
@endsection