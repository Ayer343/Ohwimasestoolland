{{-- email-accounts/show.blade.php --}}
@php
    $user = auth()->user();
    $isDeveloper = $user->isDeveloper();
    $isAdmin = $user->isAdmin();
    $isSuperAdmin = $user->isSuperAdmin();
    $isLandlord = $user->isLandlord();
    $isTenant = $user->isTenant();
    
    // Determine route prefix and layout based on user role
    if ($isSuperAdmin) {
        $routeNamePrefix = 'super-admin.email-accounts';
        $urlPath = 'super-admin/email-accounts';
        $layout = 'layouts.app';
        $pageTitle = 'Email Account Details - Super Admin';
        $backRoute = route('super-admin.email-accounts.index');
        $backIcon = 'fa-arrow-left';
        $backText = 'Back to Accounts';
    } elseif ($isDeveloper) {
        $routeNamePrefix = 'developer.email-accounts';
        $urlPath = 'developer/email-accounts';
        $layout = 'layouts.dev';
        $pageTitle = 'Email Account Details - Developer';
        $backRoute = route('developer.email-accounts.index');
        $backIcon = 'fa-arrow-left';
        $backText = 'Back to Accounts';
    } elseif ($isAdmin) {
        $routeNamePrefix = 'admin.email-accounts';
        $urlPath = 'admin/email-accounts';
        $layout = 'layouts.app';
        $pageTitle = 'Email Account Details - Admin';
        $backRoute = route('admin.email-accounts.index');
        $backIcon = 'fa-arrow-left';
        $backText = 'Back to Accounts';
    } elseif ($isLandlord) {
        $routeNamePrefix = 'landlord.email-accounts';
        $urlPath = 'landlord/email-accounts';
        $layout = 'layouts.landlord';
        $pageTitle = 'Email Account Details';
        $backRoute = route('landlord.email-accounts.index');
        $backIcon = 'fa-arrow-left';
        $backText = 'Back to Accounts';
    } elseif ($isTenant) {
        $routeNamePrefix = 'tenant.email-accounts';
        $urlPath = 'tenant/email-accounts';
        $layout = 'layouts.tenant';
        $pageTitle = 'Email Account Details';
        $backRoute = route('tenant.email-accounts.index');
        $backIcon = 'fa-arrow-left';
        $backText = 'Back to Accounts';
    } else {
        $routeNamePrefix = 'email-accounts';
        $urlPath = 'email-accounts';
        $layout = 'layouts.app';
        $pageTitle = 'Email Account Details';
        $backRoute = route('email-accounts.index');
        $backIcon = 'fa-arrow-left';
        $backText = 'Back to Accounts';
    }
    
    $emailAccount = $emailAccount ?? null;
    $stats = $emailAccount ? $emailAccount->emails()->count() : 0;
    $unreadCount = $emailAccount ? $emailAccount->emails()->where('is_read', false)->where('folder', 'INBOX')->count() : 0;
    $sentCount = $emailAccount ? $emailAccount->emails()->where('folder', 'SENT')->count() : 0;
    $draftCount = $emailAccount ? $emailAccount->emails()->where('folder', 'DRAFTS')->count() : 0;
    $trashCount = $emailAccount ? $emailAccount->emails()->where('folder', 'TRASH')->count() : 0;
    
    $errorMessage = session('error');
    $successMessage = session('success');
    $warningMessage = session('warning');
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
                        <span><i class="fas fa-info-circle mr-1"></i> View email account details</span>
                        @if($emailAccount)
                            <span class="hidden sm:inline">•</span>
                            <span><i class="fas fa-envelope mr-1"></i> {{ $emailAccount->email }}</span>
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
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Account Info Card -->
            <div class="lg:col-span-2">
                <div class="card">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-6">
                            <div>
                                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                                    Account Information
                                </h3>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    Details for your linked email account
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <a href="{{ route($routeNamePrefix . '.edit', $emailAccount->id) }}" 
                                   class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium transition-all hover:scale-105"
                                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                    <i class="fas fa-edit mr-1"></i> Edit
                                </a>
                                @if(!$emailAccount->is_primary)
                                <button onclick="setPrimary({{ $emailAccount->id }})" 
                                        class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium transition-all hover:scale-105"
                                        style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                                    <i class="fas fa-star mr-1"></i> Set Primary
                                </button>
                                @endif
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="p-4 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                                <p class="text-xs font-medium" style="color: var(--text-secondary);">Email Address</p>
                                <p class="text-sm font-semibold" style="color: var(--text-primary);">{{ $emailAccount->email }}</p>
                            </div>
                            <div class="p-4 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                                <p class="text-xs font-medium" style="color: var(--text-secondary);">Display Name</p>
                                <p class="text-sm font-semibold" style="color: var(--text-primary);">{{ $emailAccount->display_name ?? 'Not set' }}</p>
                            </div>
                            <div class="p-4 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                                <p class="text-xs font-medium" style="color: var(--text-secondary);">Provider</p>
                                <p class="text-sm font-semibold" style="color: var(--text-primary);">{{ ucfirst($emailAccount->provider) }}</p>
                            </div>
                            <div class="p-4 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                                <p class="text-xs font-medium" style="color: var(--text-secondary);">Status</p>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $emailAccount->status === 'verified' ? 'badge-success' : ($emailAccount->status === 'pending' ? 'badge-warning' : 'badge-danger') }}">
                                    @if($emailAccount->status === 'verified')
                                        <i class="fas fa-check-circle mr-0.5 text-[10px]"></i> Verified
                                    @elseif($emailAccount->status === 'pending')
                                        <i class="fas fa-clock mr-0.5 text-[10px]"></i> Pending
                                    @elseif($emailAccount->status === 'failed')
                                        <i class="fas fa-exclamation-circle mr-0.5 text-[10px]"></i> Failed
                                    @else
                                        {{ ucfirst($emailAccount->status) }}
                                    @endif
                                </span>
                            </div>
                            <div class="p-4 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                                <p class="text-xs font-medium" style="color: var(--text-secondary);">Primary Account</p>
                                <p class="text-sm font-semibold" style="color: var(--text-primary);">
                                    @if($emailAccount->is_primary)
                                        <i class="fas fa-check-circle text-green-500 mr-1"></i> Yes
                                    @else
                                        <i class="fas fa-times-circle text-gray-400 mr-1"></i> No
                                    @endif
                                </p>
                            </div>
                            <div class="p-4 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                                <p class="text-xs font-medium" style="color: var(--text-secondary);">Connection Status</p>
                                <p class="text-sm font-semibold" style="color: var(--text-primary);">
                                    @if($emailAccount->is_connected)
                                        <i class="fas fa-circle text-green-500 mr-1"></i> Connected
                                    @else
                                        <i class="fas fa-circle text-red-500 mr-1"></i> Disconnected
                                    @endif
                                </p>
                            </div>
                            <div class="p-4 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                                <p class="text-xs font-medium" style="color: var(--text-secondary);">Sync Frequency</p>
                                <p class="text-sm font-semibold" style="color: var(--text-primary);">{{ ucfirst(str_replace('_', ' ', $emailAccount->sync_frequency)) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- IMAP/SMTP Settings -->
                <div class="card mt-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                            <i class="fas fa-server mr-2" style="color: var(--primary);"></i> Server Settings
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="p-4 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                                <p class="text-xs font-medium" style="color: var(--text-secondary);">IMAP Host</p>
                                <p class="text-sm font-semibold" style="color: var(--text-primary);">{{ $emailAccount->imap_host }}</p>
                                <p class="text-xs" style="color: var(--text-secondary);">Port: {{ $emailAccount->imap_port }} | Encryption: {{ strtoupper($emailAccount->imap_encryption) }}</p>
                            </div>
                            <div class="p-4 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                                <p class="text-xs font-medium" style="color: var(--text-secondary);">SMTP Host</p>
                                <p class="text-sm font-semibold" style="color: var(--text-primary);">{{ $emailAccount->smtp_host }}</p>
                                <p class="text-xs" style="color: var(--text-secondary);">Port: {{ $emailAccount->smtp_port }} | Encryption: {{ strtoupper($emailAccount->smtp_encryption) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Card -->
            <div class="lg:col-span-1">
                <div class="card">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                            <i class="fas fa-chart-pie mr-2" style="color: var(--secondary);"></i> Statistics
                        </h3>
                        
                        <div class="space-y-3">
                            <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                                <span class="text-sm" style="color: var(--text-secondary);">
                                    <i class="fas fa-inbox mr-2" style="color: var(--primary);"></i> Total Emails
                                </span>
                                <span class="text-sm font-semibold" style="color: var(--text-primary);">{{ $stats }}</span>
                            </div>
                            <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05);">
                                <span class="text-sm" style="color: var(--text-secondary);">
                                    <i class="fas fa-envelope mr-2" style="color: var(--danger);"></i> Unread
                                </span>
                                <span class="text-sm font-semibold" style="color: var(--danger);">{{ $unreadCount }}</span>
                            </div>
                            <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05);">
                                <span class="text-sm" style="color: var(--text-secondary);">
                                    <i class="fas fa-paper-plane mr-2" style="color: var(--success);"></i> Sent
                                </span>
                                <span class="text-sm font-semibold" style="color: var(--success);">{{ $sentCount }}</span>
                            </div>
                            <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05);">
                                <span class="text-sm" style="color: var(--text-secondary);">
                                    <i class="fas fa-pen mr-2" style="color: var(--warning);"></i> Drafts
                                </span>
                                <span class="text-sm font-semibold" style="color: var(--warning);">{{ $draftCount }}</span>
                            </div>
                            <div class="flex items-center justify-between p-3 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.05);">
                                <span class="text-sm" style="color: var(--text-secondary);">
                                    <i class="fas fa-trash mr-2" style="color: var(--secondary);"></i> Trash
                                </span>
                                <span class="text-sm font-semibold" style="color: var(--text-secondary);">{{ $trashCount }}</span>
                            </div>
                        </div>

                        <hr class="my-4" style="border-color: var(--border-color);">

                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-xs" style="color: var(--text-secondary);">
                                <span>Last Sync</span>
                                <span>{{ $emailAccount->last_sync_at ? $emailAccount->last_sync_at->diffForHumans() : 'Never' }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs" style="color: var(--text-secondary);">
                                <span>Verified At</span>
                                <span>{{ $emailAccount->verified_at ? $emailAccount->verified_at->diffForHumans() : 'Never' }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs" style="color: var(--text-secondary);">
                                <span>Created</span>
                                <span>{{ $emailAccount->created_at->diffForHumans() }}</span>
                            </div>
                            @if($emailAccount->verification_error)
                            <div class="mt-2 p-2 rounded-lg text-xs" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                {{ $emailAccount->verification_error }}
                            </div>
                            @endif
                        </div>

                        <div class="mt-4 flex flex-col gap-2">
                            <button onclick="syncEmails({{ $emailAccount->id }})" 
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
            </div>
        </div>
    @endif
</div>

<!-- Sync Overlay -->
<div id="syncOverlay" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="rounded-lg shadow-xl p-8 text-center" style="background-color: var(--card-bg);">
            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"
                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                <i class="fas fa-sync fa-spin text-2xl text-white"></i>
            </div>
            <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Syncing Emails</h4>
            <p class="text-sm" style="color: var(--text-secondary);">Please wait while we sync your emails...</p>
            <div class="mt-4 w-full max-w-xs mx-auto h-1.5 rounded-full overflow-hidden" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                <div class="h-full rounded-full animate-pulse" style="width: 100%; background: linear-gradient(90deg, var(--primary), var(--secondary));"></div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
const baseUrl = '/{{ $urlPath }}';
const routePrefix = '{{ $routeNamePrefix }}';
const accountId = {{ $emailAccount ? $emailAccount->id : 'null' }};

function setPrimary(accountId) {
    if (!confirm('Set this as your primary email account?')) return;
    
    fetch(`${baseUrl}/${accountId}/set-primary`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Primary email account updated!', 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification(data.message || 'Failed to set primary account', 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred', 'error');
        console.error('Set primary error:', error);
    });
}

function syncEmails(accountId) {
    if (!accountId) return;
    
    const overlay = document.getElementById('syncOverlay');
    overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    fetch(`${baseUrl}/${accountId}/sync`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        overlay.classList.add('hidden');
        document.body.style.overflow = 'auto';
        
        if (data.success) {
            showNotification(data.message || 'Emails synced successfully!', 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification(data.message || 'Sync failed', 'error');
        }
    })
    .catch(error => {
        overlay.classList.add('hidden');
        document.body.style.overflow = 'auto';
        showNotification('An error occurred during sync', 'error');
        console.error('Sync error:', error);
    });
}

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

// CSS Styles
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    .badge-success {
        background-color: rgba(var(--success-rgb), 0.1);
        color: var(--success);
    }
    
    .badge-warning {
        background-color: rgba(var(--warning-rgb), 0.1);
        color: var(--warning);
    }
    
    .badge-danger {
        background-color: rgba(var(--danger-rgb), 0.1);
        color: var(--danger);
    }
`;
document.head.appendChild(style);

console.log('✅ Email Account Details Page Loaded');
console.log('Account ID:', accountId);
</script>
@endsection