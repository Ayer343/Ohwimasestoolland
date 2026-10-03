{{-- email-accounts/inbox.blade.php --}}
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
        $pageTitle       = 'Inbox - Super Admin';
        $backRoute       = route('super-admin.email-accounts.index');
        $backIcon        = 'fa-arrow-left';
        $backText        = 'Back to Accounts';
    } elseif ($isDeveloper) {
        $routeNamePrefix = 'developer.email-accounts';
        $urlPath         = 'developer/email-accounts';
        $layout          = 'layouts.dev';
        $pageTitle       = 'Inbox - Developer';
        $backRoute       = route('developer.email-accounts.index');
        $backIcon        = 'fa-arrow-left';
        $backText        = 'Back to Accounts';
    } elseif ($isAdmin) {
        $routeNamePrefix = 'admin.email-accounts';
        $urlPath         = 'admin/email-accounts';
        $layout          = 'layouts.app';
        $pageTitle       = 'Inbox - Admin';
        $backRoute       = route('admin.email-accounts.index');
        $backIcon        = 'fa-arrow-left';
        $backText        = 'Back to Accounts';
    } elseif ($isLandlord) {
        $routeNamePrefix = 'landlord.email-accounts';
        $urlPath         = 'landlord/email-accounts';
        $layout          = 'layouts.landlord';
        $pageTitle       = 'My Inbox';
        $backRoute       = route('landlord.email-accounts.index');
        $backIcon        = 'fa-arrow-left';
        $backText        = 'Back to Accounts';
    } elseif ($isTenant) {
        $routeNamePrefix = 'tenant.email-accounts';
        $urlPath         = 'tenant/email-accounts';
        $layout          = 'layouts.tenant';
        $pageTitle       = 'My Inbox';
        $backRoute       = route('tenant.email-accounts.index');
        $backIcon        = 'fa-arrow-left';
        $backText        = 'Back to Accounts';
    } else {
        $routeNamePrefix = 'email-accounts';
        $urlPath         = 'email-accounts';
        $layout          = 'layouts.app';
        $pageTitle       = 'Inbox';
        $backRoute       = route('email-accounts.index');
        $backIcon        = 'fa-arrow-left';
        $backText        = 'Back to Accounts';
    }

    $emailAccount = $emailAccount ?? null;
    $emails       = $emails ?? collect();

    $unreadCount = $emailAccount
        ? $emailAccount->emails()->where('is_read', false)->where('folder', 'INBOX')->count()
        : 0;
    $totalCount  = $emailAccount
        ? $emailAccount->emails()->where('folder', 'INBOX')->count()
        : 0;

    $folders = [
        'INBOX'  => ['label' => 'Inbox',  'icon' => 'fa-inbox',       'count' => $totalCount],
        'SENT'   => ['label' => 'Sent',   'icon' => 'fa-paper-plane', 'count' => $emailAccount ? $emailAccount->emails()->where('folder', 'SENT')->count() : 0],
        'DRAFTS' => ['label' => 'Drafts', 'icon' => 'fa-pen',         'count' => $emailAccount ? $emailAccount->emails()->where('folder', 'DRAFTS')->count() : 0],
        'TRASH'  => ['label' => 'Trash',  'icon' => 'fa-trash',       'count' => $emailAccount ? $emailAccount->emails()->where('folder', 'TRASH')->count() : 0],
    ];

    $currentFolder = request()->get('folder', 'INBOX');

    $errorMessage   = session('error');
    $successMessage = session('success');
    $warningMessage = session('warning');
@endphp

@php
    if (!function_exists('formatEmailDate')) {
        function formatEmailDate($date) {
            if (!$date) return 'N/A';
            $diff = now()->diff($date);
            if ($diff->days > 7) {
                return $date->format('M j, Y');
            } elseif ($diff->days > 0) {
                return $diff->days . 'd';
            } elseif ($diff->h > 0) {
                return $diff->h . 'h';
            } elseif ($diff->i > 0) {
                return $diff->i . 'm';
            } else {
                return 'Just now';
            }
        }
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
                        <i class="fas fa-inbox text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-inbox mr-2" style="color: var(--primary);"></i>
                        {{ $pageTitle }}
                    </h2>
                    <div class="text-sm flex flex-wrap items-center mt-1 gap-2" style="color: var(--text-secondary);">
                        <span><i class="fas fa-info-circle mr-1"></i> {{ $emailAccount ? $emailAccount->email : 'No account selected' }}</span>
                        @if($emailAccount)
                            <span class="hidden sm:inline">•</span>
                            <span>
                                <i class="fas fa-envelope mr-1"></i>
                                {{ $totalCount }} total emails
                            </span>
                            @if($unreadCount > 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs" style="background-color: rgba(var(--danger-rgb), 0.2); color: var(--danger);">
                                    <i class="fas fa-circle text-[6px] mr-1"></i>
                                    {{ $unreadCount }} unread
                                </span>
                            @endif
                            @if($emailAccount->is_primary)
                                <span class="text-xs px-1.5 py-0.5 rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                    <i class="fas fa-star mr-0.5"></i> Primary
                                </span>
                            @endif
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

    @if(!$emailAccount)
        <!-- No Account Warning -->
        <div class="card">
            <div class="text-center py-12">
                <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4"
                     style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-exclamation-triangle text-2xl" style="color: var(--warning);"></i>
                </div>
                <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                    No Email Account Linked
                </h4>
                <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                    Please link an email account to view your inbox.
                </p>
                <a href="{{ route($routeNamePrefix . '.create') }}"
                   class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-white transition-all hover:scale-105"
                   style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                    <i class="fas fa-link mr-2"></i> Link Your First Email
                </a>
            </div>
        </div>
    @else
        <!-- Main Inbox Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Sidebar - Folders -->
            <div class="lg:col-span-1">
                <div class="card">
                    <div class="p-4">
                        <div class="mb-4">
                            <button onclick="syncEmails({{ $emailAccount->id }})"
                                    class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium text-white transition-all hover:scale-105"
                                    style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                                <i class="fas fa-sync mr-2"></i> Sync Now
                            </button>
                        </div>

                        <div class="space-y-1">
                            <a href="{{ route($routeNamePrefix . '.inbox', ['folder' => 'INBOX']) }}"
                               class="flex items-center justify-between px-3 py-2 rounded-lg transition-all {{ $currentFolder === 'INBOX' ? 'active' : '' }}"
                               style="{{ $currentFolder === 'INBOX' ? 'background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);' : 'color: var(--text-secondary);' }}">
                                <span class="flex items-center">
                                    <i class="fas fa-inbox mr-3 w-4 text-center" style="{{ $currentFolder === 'INBOX' ? 'color: var(--primary);' : '' }}"></i>
                                    Inbox
                                </span>
                                <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                    {{ $folders['INBOX']['count'] }}
                                </span>
                            </a>

                            <a href="{{ route($routeNamePrefix . '.sent') }}"
                               class="flex items-center justify-between px-3 py-2 rounded-lg transition-all {{ $currentFolder === 'SENT' ? 'active' : '' }}"
                               style="{{ $currentFolder === 'SENT' ? 'background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);' : 'color: var(--text-secondary);' }}">
                                <span class="flex items-center">
                                    <i class="fas fa-paper-plane mr-3 w-4 text-center"></i>
                                    Sent
                                </span>
                                <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                    {{ $folders['SENT']['count'] }}
                                </span>
                            </a>

                            <a href="#"
                               class="flex items-center justify-between px-3 py-2 rounded-lg transition-all"
                               style="color: var(--text-secondary); opacity: 0.6; cursor: not-allowed;">
                                <span class="flex items-center">
                                    <i class="fas fa-pen mr-3 w-4 text-center"></i>
                                    Drafts
                                </span>
                                <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                    {{ $folders['DRAFTS']['count'] }}
                                </span>
                            </a>

                            <a href="#"
                               class="flex items-center justify-between px-3 py-2 rounded-lg transition-all"
                               style="color: var(--text-secondary); opacity: 0.6; cursor: not-allowed;">
                                <span class="flex items-center">
                                    <i class="fas fa-trash mr-3 w-4 text-center"></i>
                                    Trash
                                </span>
                                <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                    {{ $folders['TRASH']['count'] }}
                                </span>
                            </a>
                        </div>

                        <hr class="my-4" style="border-color: var(--border-color);">

                        <div class="space-y-2">
                            <div class="px-3 py-2">
                                <div class="flex justify-between text-xs" style="color: var(--text-secondary);">
                                    <span>Storage Used</span>
                                    <span>0%</span>
                                </div>
                                <div class="w-full h-1.5 rounded-full mt-1 overflow-hidden" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                                    <div class="h-full rounded-full" style="width: 0%; background: linear-gradient(90deg, var(--primary), var(--secondary));"></div>
                                </div>
                            </div>

                            <a href="{{ route($routeNamePrefix . '.compose') }}"
                               class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium text-white transition-all hover:scale-105"
                               style="background: linear-gradient(135deg, var(--success) 0%, var(--primary) 100%);">
                                <i class="fas fa-plus-circle mr-2"></i> Compose
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Email List -->
            <div class="lg:col-span-3">
                <div class="card">
                    <!-- Toolbar -->
                    <div class="flex flex-wrap items-center justify-between gap-3 p-4 border-b" style="border-color: var(--border-color);">
                        <div class="flex flex-wrap items-center gap-2">
                            <button onclick="selectAll()"
                                    class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
                                    style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.2);">
                                <i class="fas fa-check-double mr-1"></i> Select All
                            </button>

                            <button onclick="markAsRead()"
                                    class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
                                    style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.2);">
                                <i class="fas fa-check-circle mr-1"></i> Read
                            </button>

                            <button onclick="markAsUnread()"
                                    class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
                                    style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                                <i class="fas fa-circle mr-1"></i> Unread
                            </button>

                            <button onclick="deleteSelected()"
                                    class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                                <i class="fas fa-trash mr-1"></i> Delete
                            </button>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-xs" style="color: var(--text-secondary);">
                                {{ $emails->firstItem() ?? 0 }} - {{ $emails->lastItem() ?? 0 }} of {{ $emails->total() ?? 0 }}
                            </span>
                        </div>
                    </div>

                    <!-- Email List -->
                    @if($emails->isEmpty())
                        <div class="text-center py-12">
                            <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4"
                                 style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                <i class="fas fa-inbox text-2xl" style="color: var(--secondary);"></i>
                            </div>
                            <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                                No emails found
                            </h4>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                Your inbox is empty. Click "Sync Now" to fetch new emails.
                            </p>
                        </div>
                    @else
                        <div class="divide-y" style="border-color: var(--border-color);">
                            @foreach($emails as $email)
                                <div class="email-item hover:bg-gray-50 dark:hover:bg-gray-800 transition-all {{ !$email->is_read ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}"
                                     data-email-id="{{ $email->id }}"
                                     onclick="window.location.href='{{ route($routeNamePrefix . '.view-email', [$emailAccount->id, $email->id]) }}'"
                                     style="cursor: pointer;">
                                    <div class="flex items-center gap-3 p-4">
                                        <!-- Checkbox -->
                                        <div onclick="event.stopPropagation(); toggleSelect(this)" class="flex-shrink-0">
                                            <input type="checkbox" class="email-checkbox rounded transition-all" style="accent-color: var(--primary);">
                                        </div>

                                        <!-- Unread indicator dot -->
                                        <div class="flex-shrink-0">
                                            @if(!$email->is_read)
                                                <div class="w-3 h-3 rounded-full" style="background-color: var(--primary);"></div>
                                            @else
                                                <div class="w-3 h-3 rounded-full" style="background-color: transparent;"></div>
                                            @endif
                                        </div>

                                        <!-- Sender -->
                                        <div class="flex-shrink-0 min-w-[120px] md:min-w-[180px]">
                                            <p class="text-sm font-medium truncate" style="color: var(--text-primary);">
                                                {{ $email->from_name ?? $email->from_email ?? 'Unknown Sender' }}
                                            </p>
                                        </div>

                                        <!-- Subject & Preview -->
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm truncate" style="color: var(--text-primary);">
                                                <span class="font-medium">{{ $email->subject ?? '(No Subject)' }}</span>
                                                <span class="text-gray-400 dark:text-gray-500"> - </span>
                                                <span class="text-gray-500 dark:text-gray-400">{{ Str::limit(strip_tags($email->body ?? ''), 80) }}</span>
                                            </p>
                                        </div>

                                        <!-- Attachments Indicator -->
                                        @if($email->has_attachments ?? false)
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-paperclip text-gray-400"></i>
                                            </div>
                                        @endif

                                        <!-- Date -->
                                        <div class="flex-shrink-0 text-xs whitespace-nowrap" style="color: var(--text-secondary);">
                                            {{ formatEmailDate($email->received_at ?? $email->created_at) }}
                                        </div>

                                        <!-- Actions Dropdown -->
                                        <div class="flex-shrink-0" onclick="event.stopPropagation();">
                                            <div class="relative inline-block">
                                                <button onclick="toggleDropdown(this)"
                                                        class="p-1.5 rounded-lg transition-all hover:bg-gray-200 dark:hover:bg-gray-700">
                                                    <i class="fas fa-ellipsis-v" style="color: var(--text-secondary);"></i>
                                                </button>
                                                <div class="dropdown-menu hidden absolute right-0 mt-1 w-48 rounded-lg shadow-lg py-1 z-10"
                                                     style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                                                    <button onclick="markAsReadSingle('{{ $email->id }}')"
                                                            class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                                            style="color: var(--text-primary);">
                                                        <i class="fas fa-check-circle mr-2" style="color: var(--info);"></i> Mark as Read
                                                    </button>
                                                    <button onclick="markAsUnreadSingle('{{ $email->id }}')"
                                                            class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                                            style="color: var(--text-primary);">
                                                        <i class="fas fa-circle mr-2" style="color: var(--warning);"></i> Mark as Unread
                                                    </button>
                                                    <hr style="border-color: var(--border-color);">
                                                    <button onclick="deleteEmail('{{ $email->id }}')"
                                                            class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                                            style="color: var(--danger);">
                                                        <i class="fas fa-trash mr-2"></i> Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <!-- Pagination -->
                    @if(method_exists($emails, 'links') && $emails->hasPages())
                        <div class="p-4 border-t" style="border-color: var(--border-color);">
                            {{ $emails->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70" onclick="hideDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="rounded-lg shadow-xl w-full max-w-md" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center p-5 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-trash mr-2" style="color: var(--danger);"></i> Delete Email
                </h3>
                <button type="button" onclick="hideDeleteModal()" class="transition" style="color: var(--text-secondary);">
                    <i class="fas fa-times hover:text-red-500"></i>
                </button>
            </div>
            <div class="p-5">
                <p class="text-sm" id="deleteMessage" style="color: var(--text-secondary);"></p>
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
                    <i class="fas fa-trash mr-2"></i> Delete
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
            <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);" id="syncOverlayTitle">Syncing Emails</h4>
            <p class="text-sm" id="syncOverlayMessage" style="color: var(--text-secondary);">Please wait while we sync your emails…</p>
            <div class="mt-4 w-full max-w-xs mx-auto h-1.5 rounded-full overflow-hidden" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                <div class="h-full rounded-full animate-pulse" style="width: 100%; background: linear-gradient(90deg, var(--primary), var(--secondary));"></div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
const baseUrl       = '/{{ $urlPath }}';
const routePrefix   = '{{ $routeNamePrefix }}';
const csrfToken     = '{{ csrf_token() }}';
const accountId     = {{ $emailAccount ? $emailAccount->id : 'null' }};
const currentFolder = '{{ $currentFolder }}';

let deleteEmailId = null;
let selectedEmails = new Set();

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
        throw new Error(data.message || `HTTP ${response.status}`);
    }

    return data;
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
// 📨 SYNC EMAILS                              //
// ========================================== //
function syncEmails(id) {
    if (!id) return;

    showOverlay('Syncing Emails', 'Please wait while we sync your emails…');

    apiFetch(`${baseUrl}/${id}/sync`, { method: 'POST' })
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

// ========================================== //
// 📋 SELECT ALL / BULK ACTIONS               //
// ========================================== //
function selectAll() {
    const checkboxes = document.querySelectorAll('.email-checkbox');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);

    checkboxes.forEach(cb => {
        cb.checked = !allChecked;
        const item = cb.closest('.email-item');
        if (item) {
            if (cb.checked) {
                selectedEmails.add(item.dataset.emailId);
                item.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
            } else {
                selectedEmails.delete(item.dataset.emailId);
                item.style.backgroundColor = '';
            }
        }
    });
}

function toggleSelect(element) {
    const checkbox = element.querySelector('.email-checkbox');
    const item = checkbox.closest('.email-item');

    if (checkbox.checked) {
        selectedEmails.add(item.dataset.emailId);
        item.style.backgroundColor = 'rgba(var(--primary-rgb), 0.05)';
    } else {
        selectedEmails.delete(item.dataset.emailId);
        item.style.backgroundColor = '';
    }
}

function getSelectedIds() {
    return Array.from(selectedEmails);
}

async function markAsRead() {
    const ids = getSelectedIds();
    if (ids.length === 0) {
        showNotification('Please select at least one email', 'warning');
        return;
    }

    showOverlay('Marking as Read', `Updating ${ids.length} email(s)…`);

    const results = await Promise.allSettled(ids.map(id =>
        apiFetch(`${baseUrl}/${accountId}/emails/${id}/mark-read`, { method: 'POST' })
    ));

    hideOverlay();

    const succeeded = results.filter(r => r.status === 'fulfilled').length;
    showNotification(`${succeeded}/${ids.length} email(s) marked as read`, succeeded === ids.length ? 'success' : 'warning');
    setTimeout(() => window.location.reload(), 1200);
}

async function markAsUnread() {
    const ids = getSelectedIds();
    if (ids.length === 0) {
        showNotification('Please select at least one email', 'warning');
        return;
    }

    showOverlay('Marking as Unread', `Updating ${ids.length} email(s)…`);

    const results = await Promise.allSettled(ids.map(id =>
        apiFetch(`${baseUrl}/${accountId}/emails/${id}/mark-unread`, { method: 'POST' })
    ));

    hideOverlay();

    const succeeded = results.filter(r => r.status === 'fulfilled').length;
    showNotification(`${succeeded}/${ids.length} email(s) marked as unread`, succeeded === ids.length ? 'success' : 'warning');
    setTimeout(() => window.location.reload(), 1200);
}

async function deleteSelected() {
    const ids = getSelectedIds();
    if (ids.length === 0) {
        showNotification('Please select at least one email', 'warning');
        return;
    }

    if (!confirm(`Delete ${ids.length} selected email(s)?`)) return;

    showOverlay('Deleting', `Deleting ${ids.length} email(s)…`);

    const results = await Promise.allSettled(ids.map(id =>
        apiFetch(`${baseUrl}/${accountId}/emails/${id}`, { method: 'DELETE' })
    ));

    hideOverlay();

    const succeeded = results.filter(r => r.status === 'fulfilled').length;
    showNotification(`${succeeded}/${ids.length} email(s) deleted`, succeeded === ids.length ? 'success' : 'warning');
    setTimeout(() => window.location.reload(), 1200);
}

// ========================================== //
// 📝 SINGLE EMAIL ACTIONS                    //
// ========================================== //
function markAsReadSingle(emailId) {
    apiFetch(`${baseUrl}/${accountId}/emails/${emailId}/mark-read`, { method: 'POST' })
        .then(data => {
            if (data.success) {
                showNotification('Email marked as read', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showNotification(data.message || 'Failed to mark as read', 'error');
            }
        })
        .catch(error => {
            showNotification('Failed to mark as read: ' + error.message, 'error');
        });
}

function markAsUnreadSingle(emailId) {
    apiFetch(`${baseUrl}/${accountId}/emails/${emailId}/mark-unread`, { method: 'POST' })
        .then(data => {
            if (data.success) {
                showNotification('Email marked as unread', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showNotification(data.message || 'Failed to mark as unread', 'error');
            }
        })
        .catch(error => {
            showNotification('Failed to mark as unread: ' + error.message, 'error');
        });
}

function deleteEmail(emailId) {
    deleteEmailId = emailId;

    const modal = document.getElementById('deleteModal');
    const message = document.getElementById('deleteMessage');
    message.textContent = 'Are you sure you want to delete this email? This action cannot be undone.';

    const confirmBtn = document.getElementById('confirmDeleteBtn');
    confirmBtn.onclick = function () {
        executeDelete(emailId);
    };

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function executeDelete(emailId) {
    hideDeleteModal();

    apiFetch(`${baseUrl}/${accountId}/emails/${emailId}`, { method: 'DELETE' })
        .then(data => {
            if (data.success) {
                showNotification('Email deleted successfully', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showNotification(data.message || 'Failed to delete email', 'error');
            }
        })
        .catch(error => {
            showNotification('Delete failed: ' + error.message, 'error');
            console.error('Delete error:', error);
        });
}

function hideDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// ========================================== //
// 🔽 DROPDOWN TOGGLE                        //
// ========================================== //
function toggleDropdown(button) {
    const dropdown = button.closest('.relative').querySelector('.dropdown-menu');
    const isHidden = dropdown.classList.contains('hidden');

    document.querySelectorAll('.dropdown-menu').forEach(d => d.classList.add('hidden'));

    if (isHidden) {
        dropdown.classList.remove('hidden');
    }
}

document.addEventListener('click', function (e) {
    if (!e.target.closest('.relative')) {
        document.querySelectorAll('.dropdown-menu').forEach(d => d.classList.add('hidden'));
    }
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
// ⌨️ KEYBOARD SHORTCUTS                      //
// ========================================== //
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        hideDeleteModal();
    }

    if (e.ctrlKey && e.key === 'a') {
        e.preventDefault();
        selectAll();
    }

    if (e.key === 'r' && !e.ctrlKey && !e.metaKey) {
        if (!e.target.closest('input, textarea, select')) {
            e.preventDefault();
            if (accountId) syncEmails(accountId);
        }
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

    .email-item { transition: background-color 0.2s ease; }
    .email-item:hover { background-color: rgba(var(--secondary-rgb), 0.05); }

    .email-item .dropdown-menu { animation: fadeIn 0.15s ease-out; }

    @keyframes fadeIn {
        from { opacity: 0; transform: scale(0.95); }
        to   { opacity: 1; transform: scale(1); }
    }

    .email-list-scroll {
        scrollbar-width: thin;
        scrollbar-color: var(--border-color) transparent;
    }

    .email-list-scroll::-webkit-scrollbar { width: 6px; }
    .email-list-scroll::-webkit-scrollbar-track { background: transparent; }
    .email-list-scroll::-webkit-scrollbar-thumb {
        background-color: var(--border-color);
        border-radius: 3px;
    }

    @media (prefers-color-scheme: dark) {
        .email-item.bg-blue-50 { background-color: rgba(59, 130, 246, 0.15) !important; }
    }
`;
document.head.appendChild(style);

console.log('✅ Inbox Page Loaded');
console.log('Account ID:', accountId);
console.log('Current Folder:', currentFolder);
</script>
@endsection