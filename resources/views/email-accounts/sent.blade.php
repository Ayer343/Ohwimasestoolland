{{-- email-accounts/sent.blade.php --}}
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
        $pageTitle = 'Sent Emails - Super Admin';
        $backRoute = route('super-admin.email-accounts.index');
        $backIcon = 'fa-arrow-left';
        $backText = 'Back to Accounts';
    } elseif ($isDeveloper) {
        $routeNamePrefix = 'developer.email-accounts';
        $urlPath = 'developer/email-accounts';
        $layout = 'layouts.dev';
        $pageTitle = 'Sent Emails - Developer';
        $backRoute = route('developer.email-accounts.index');
        $backIcon = 'fa-arrow-left';
        $backText = 'Back to Accounts';
    } elseif ($isAdmin) {
        $routeNamePrefix = 'admin.email-accounts';
        $urlPath = 'admin/email-accounts';
        $layout = 'layouts.app';
        $pageTitle = 'Sent Emails - Admin';
        $backRoute = route('admin.email-accounts.index');
        $backIcon = 'fa-arrow-left';
        $backText = 'Back to Accounts';
    } elseif ($isLandlord) {
        $routeNamePrefix = 'landlord.email-accounts';
        $urlPath = 'landlord/email-accounts';
        $layout = 'layouts.landlord';
        $pageTitle = 'Sent Emails';
        $backRoute = route('landlord.email-accounts.index');
        $backIcon = 'fa-arrow-left';
        $backText = 'Back to Accounts';
    } elseif ($isTenant) {
        $routeNamePrefix = 'tenant.email-accounts';
        $urlPath = 'tenant/email-accounts';
        $layout = 'layouts.tenant';
        $pageTitle = 'Sent Emails';
        $backRoute = route('tenant.email-accounts.index');
        $backIcon = 'fa-arrow-left';
        $backText = 'Back to Accounts';
    } else {
        $routeNamePrefix = 'email-accounts';
        $urlPath = 'email-accounts';
        $layout = 'layouts.app';
        $pageTitle = 'Sent Emails';
        $backRoute = route('email-accounts.index');
        $backIcon = 'fa-arrow-left';
        $backText = 'Back to Accounts';
    }
    
    $emailAccount = $emailAccount ?? null;
    $emails = $emails ?? collect();
    
    // ✅ FIX: Safely get sent count with try-catch for missing status column
    try {
        $sentCount = $emailAccount ? $emailAccount->emails()->where('folder', 'SENT')->count() : 0;
    } catch (\Exception $e) {
        // Fallback if status column doesn't exist
        $sentCount = $emailAccount ? $emailAccount->emails()->where('folder', 'SENT')->count() : 0;
    }
    
    // Get folders with counts
    $folders = [
        'INBOX' => ['label' => 'Inbox', 'icon' => 'fa-inbox', 'route' => 'inbox', 'count' => $emailAccount ? $emailAccount->emails()->where('folder', 'INBOX')->count() : 0],
        'SENT' => ['label' => 'Sent', 'icon' => 'fa-paper-plane', 'route' => 'sent', 'count' => $sentCount],
        'DRAFTS' => ['label' => 'Drafts', 'icon' => 'fa-pen', 'route' => 'drafts', 'count' => $emailAccount ? $emailAccount->emails()->where('folder', 'DRAFTS')->count() : 0],
        'TRASH' => ['label' => 'Trash', 'icon' => 'fa-trash', 'route' => 'trash', 'count' => $emailAccount ? $emailAccount->emails()->where('folder', 'TRASH')->count() : 0],
    ];
    
    // ✅ FIX: Safely get status counts with try-catch for missing status column
    try {
        $statusCounts = [
            'sent' => $emailAccount ? $emailAccount->emails()->where('folder', 'SENT')->where('status', 'sent')->count() : 0,
            'delivered' => $emailAccount ? $emailAccount->emails()->where('folder', 'SENT')->where('status', 'delivered')->count() : 0,
            'read' => $emailAccount ? $emailAccount->emails()->where('folder', 'SENT')->where('status', 'read')->count() : 0,
            'failed' => $emailAccount ? $emailAccount->emails()->where('folder', 'SENT')->where('status', 'failed')->count() : 0,
            'pending' => $emailAccount ? $emailAccount->emails()->where('folder', 'SENT')->where('status', 'pending')->count() : 0,
        ];
    } catch (\Exception $e) {
        // Fallback if status column doesn't exist yet
        $statusCounts = [
            'sent' => $sentCount,
            'delivered' => 0,
            'read' => 0,
            'failed' => 0,
            'pending' => 0,
        ];
    }
    
    $currentFolder = 'SENT';
    
    $errorMessage = session('error');
    $successMessage = session('success');
    $warningMessage = session('warning');
    
    // Format email date helper
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
    
    // Get status badge - WITH FALLBACK FOR MISSING STATUS
    function getStatusBadge($status) {
        $statusMap = [
            'sent' => ['label' => 'Sent', 'color' => 'success', 'icon' => 'fa-check-circle'],
            'delivered' => ['label' => 'Delivered', 'color' => 'info', 'icon' => 'fa-check-double'],
            'read' => ['label' => 'Read', 'color' => 'primary', 'icon' => 'fa-eye'],
            'failed' => ['label' => 'Failed', 'color' => 'danger', 'icon' => 'fa-exclamation-circle'],
            'pending' => ['label' => 'Pending', 'color' => 'warning', 'icon' => 'fa-clock'],
        ];
        return $statusMap[$status] ?? ['label' => 'Sent', 'color' => 'success', 'icon' => 'fa-check-circle'];
    }
    
    // Get priority badge
    function getPriorityBadge($priority) {
        $priorityMap = [
            'high' => ['label' => 'High', 'color' => 'danger', 'icon' => 'fa-flag'],
            'low' => ['label' => 'Low', 'color' => 'secondary', 'icon' => 'fa-flag'],
            'normal' => ['label' => 'Normal', 'color' => 'info', 'icon' => 'fa-flag'],
        ];
        return $priorityMap[$priority] ?? $priorityMap['normal'];
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
                         style="background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%); color: white; border-color: var(--secondary);">
                        <i class="fas fa-paper-plane text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-paper-plane mr-2" style="color: var(--secondary);"></i> 
                        {{ $pageTitle }}
                    </h2>
                    <div class="text-sm flex flex-wrap items-center mt-1 gap-2" style="color: var(--text-secondary);">
                        <span><i class="fas fa-info-circle mr-1"></i> {{ $emailAccount ? $emailAccount->email : 'No account selected' }}</span>
                        @if($emailAccount)
                            <span class="hidden sm:inline">•</span>
                            <span>
                                <i class="fas fa-envelope mr-1"></i> 
                                {{ $sentCount }} sent emails
                            </span>
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
                    No Email Account Linked
                </h4>
                <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                    Please link an email account to view your sent emails.
                </p>
                <a href="{{ route($routeNamePrefix . '.create') }}" 
                   class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-white transition-all hover:scale-105"
                   style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                    <i class="fas fa-link mr-2"></i> Link Your First Email
                </a>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <div class="card">
                    <div class="p-4">
                        <div class="mb-4">
                            <a href="{{ route($routeNamePrefix . '.compose') }}" 
                               class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium text-white transition-all hover:scale-105"
                               style="background: linear-gradient(135deg, var(--success) 0%, var(--primary) 100%);">
                                <i class="fas fa-plus-circle mr-2"></i> Compose
                            </a>
                        </div>
                        
                        <div class="space-y-1">
                            <a href="{{ route($routeNamePrefix . '.inbox') }}" 
                               class="flex items-center justify-between px-3 py-2 rounded-lg transition-all"
                               style="color: var(--text-secondary);">
                                <span class="flex items-center">
                                    <i class="fas fa-inbox mr-3 w-4 text-center"></i>
                                    Inbox
                                </span>
                                <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                    {{ $folders['INBOX']['count'] }}
                                </span>
                            </a>
                            
                            <a href="{{ route($routeNamePrefix . '.sent') }}" 
                               class="flex items-center justify-between px-3 py-2 rounded-lg transition-all active"
                               style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                <span class="flex items-center">
                                    <i class="fas fa-paper-plane mr-3 w-4 text-center" style="color: var(--primary);"></i>
                                    Sent
                                </span>
                                <span class="text-xs px-2 py-0.5 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.2); color: var(--primary);">
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
                        
                        <!-- Status Statistics - WITH FALLBACK -->
                        <div class="space-y-3">
                            <div class="px-3 py-2">
                                <div class="flex justify-between text-xs" style="color: var(--text-secondary);">
                                    <span>Daily Send Quota</span>
                                    <span>{{ $emailAccount->emails_sent_today ?? 0 }} / {{ $emailAccount->daily_send_limit ?? 500 }}</span>
                                </div>
                                <div class="w-full h-1.5 rounded-full mt-1 overflow-hidden" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                                    <div class="h-full rounded-full transition-all" 
                                         style="width: {{ min(100, (($emailAccount->emails_sent_today ?? 0) / ($emailAccount->daily_send_limit ?? 500)) * 100) }}%; 
                                                background: linear-gradient(90deg, var(--primary), var(--secondary));">
                                    </div>
                                </div>
                            </div>
                            
                            @if(isset($statusCounts) && array_sum($statusCounts) > 0)
                                <div class="flex items-center justify-between px-3 py-1.5 text-xs" style="color: var(--text-secondary);">
                                    <span><i class="fas fa-check-circle text-green-500 mr-1"></i> Sent</span>
                                    <span>{{ $statusCounts['sent'] ?? 0 }}</span>
                                </div>
                                <div class="flex items-center justify-between px-3 py-1.5 text-xs" style="color: var(--text-secondary);">
                                    <span><i class="fas fa-check-double text-blue-500 mr-1"></i> Delivered</span>
                                    <span>{{ $statusCounts['delivered'] ?? 0 }}</span>
                                </div>
                                <div class="flex items-center justify-between px-3 py-1.5 text-xs" style="color: var(--text-secondary);">
                                    <span><i class="fas fa-eye text-primary-500 mr-1"></i> Read</span>
                                    <span>{{ $statusCounts['read'] ?? 0 }}</span>
                                </div>
                                <div class="flex items-center justify-between px-3 py-1.5 text-xs" style="color: var(--text-secondary);">
                                    <span><i class="fas fa-exclamation-circle text-red-500 mr-1"></i> Failed</span>
                                    <span>{{ $statusCounts['failed'] ?? 0 }}</span>
                                </div>
                                <div class="flex items-center justify-between px-3 py-1.5 text-xs" style="color: var(--text-secondary);">
                                    <span><i class="fas fa-clock text-yellow-500 mr-1"></i> Pending</span>
                                    <span>{{ $statusCounts['pending'] ?? 0 }}</span>
                                </div>
                            @else
                                <div class="text-center py-2 text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-info-circle mr-1"></i> 
                                    Status tracking available after migration
                                </div>
                            @endif
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
                            
                            <button onclick="resendSelected()" 
                                    class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
                                    style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                                <i class="fas fa-redo mr-1"></i> Resend
                            </button>
                            
                            <button onclick="deleteSelected()" 
                                    class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all"
                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                                <i class="fas fa-trash mr-1"></i> Delete
                            </button>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            @if(isset($statusCounts) && array_sum($statusCounts) > 0)
                                <div class="flex items-center gap-2 text-xs" style="color: var(--text-secondary);">
                                    <i class="fas fa-filter mr-1"></i>
                                    <select onchange="filterEmails(this.value)" 
                                            class="rounded-lg px-2 py-1 text-xs transition-all focus:ring-1 focus:outline-none"
                                            style="background-color: var(--input-bg); color: var(--text-primary); border: 1px solid var(--border-color);">
                                        <option value="all">All</option>
                                        <option value="sent">Sent</option>
                                        <option value="delivered">Delivered</option>
                                        <option value="read">Read</option>
                                        <option value="failed">Failed</option>
                                        <option value="pending">Pending</option>
                                    </select>
                                </div>
                            @endif
                            
                            <span class="text-xs" style="color: var(--text-secondary);">
                                {{ $emails->firstItem() ?? 0 }} - {{ $emails->lastItem() ?? 0 }} of {{ $emails->total() ?? 0 }}
                            </span>
                            @if(method_exists($emails, 'links'))
                                <div class="flex">
                                    {{ $emails->links() }}
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Email List -->
                    @if($emails->isEmpty())
                        <div class="text-center py-12">
                            <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" 
                                 style="background-color: rgba(var(--secondary-rgb), 0.1);">
                                <i class="fas fa-paper-plane text-2xl" style="color: var(--secondary);"></i>
                            </div>
                            <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                                No sent emails
                            </h4>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                You haven't sent any emails yet. Compose your first email now.
                            </p>
                            <div class="mt-4">
                                <a href="{{ route($routeNamePrefix . '.compose') }}" 
                                   class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-white transition-all hover:scale-105"
                                   style="background: linear-gradient(135deg, var(--success) 0%, var(--primary) 100%);">
                                    <i class="fas fa-plus-circle mr-2"></i> Compose Email
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="divide-y" style="border-color: var(--border-color);">
                            @foreach($emails as $email)
                                @php
                                    $status = getStatusBadge($email->status ?? 'sent');
                                    $priorityBadge = getPriorityBadge($email->priority ?? 'normal');
                                    $hasAttachments = ($email->attachment_count ?? 0) > 0;
                                @endphp
                                <div class="email-item hover:bg-gray-50 dark:hover:bg-gray-800 transition-all"
                                     data-email-id="{{ $email->id }}"
                                     onclick="window.location.href='{{ route($routeNamePrefix . '.view-email', [$emailAccount->id, $email->id]) }}'"
                                     style="cursor: pointer;">
                                    <div class="flex items-center gap-3 p-4">
                                        <!-- Checkbox -->
                                        <div onclick="event.stopPropagation(); toggleSelect(this)" class="flex-shrink-0">
                                            <input type="checkbox" class="email-checkbox rounded transition-all" style="accent-color: var(--primary);">
                                        </div>
                                        
                                        <!-- Status Icon -->
                                        <div class="flex-shrink-0" title="{{ $status['label'] }}">
                                            <i class="fas {{ $status['icon'] }}" style="color: var(--{{ $status['color'] }});"></i>
                                        </div>
                                        
                                        <!-- Priority Icon -->
                                        @if(($email->priority ?? 'normal') === 'high')
                                            <div class="flex-shrink-0" title="High Priority">
                                                <i class="fas fa-flag" style="color: var(--danger);"></i>
                                            </div>
                                        @elseif(($email->priority ?? 'normal') === 'low')
                                            <div class="flex-shrink-0" title="Low Priority">
                                                <i class="fas fa-flag" style="color: var(--text-secondary); opacity: 0.5;"></i>
                                            </div>
                                        @endif
                                        
                                        <!-- Recipient -->
                                        <div class="flex-shrink-0 min-w-[120px] md:min-w-[180px]">
                                            <p class="text-sm font-medium truncate" style="color: var(--text-primary);">
                                                To: {{ $email->to_email ?? 'Unknown Recipient' }}
                                            </p>
                                            @if($email->to_name)
                                                <p class="text-xs truncate" style="color: var(--text-secondary);">
                                                    {{ $email->to_name }}
                                                </p>
                                            @endif
                                        </div>
                                        
                                        <!-- Subject & Preview -->
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm truncate" style="color: var(--text-primary);">
                                                <span class="font-medium">{{ $email->subject ?? '(No Subject)' }}</span>
                                                <span class="text-gray-400 dark:text-gray-500"> - </span>
                                                <span class="text-gray-500 dark:text-gray-400">{{ Str::limit(strip_tags($email->body ?? ''), 60) }}</span>
                                            </p>
                                            @if(($email->status ?? '') === 'failed' && $email->metadata)
                                                <p class="text-xs truncate" style="color: var(--danger);">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                                    {{ Str::limit(json_encode($email->metadata) ?? 'Unknown error', 50) }}
                                                </p>
                                            @endif
                                        </div>
                                        
                                        <!-- Status Badge -->
                                        <div class="flex-shrink-0 hidden sm:block">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium badge-{{ $status['color'] }}">
                                                <i class="fas {{ $status['icon'] }} mr-0.5 text-[10px]"></i>
                                                {{ $status['label'] }}
                                            </span>
                                        </div>
                                        
                                        <!-- Attachments Indicator -->
                                        @if($hasAttachments)
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-paperclip text-gray-400"></i>
                                            </div>
                                        @endif
                                        
                                        <!-- Date -->
                                        <div class="flex-shrink-0 text-xs whitespace-nowrap" style="color: var(--text-secondary);">
                                            {{ formatEmailDate($email->sent_at ?? $email->created_at) }}
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
                                                    <button onclick="resendEmail('{{ $email->id }}')" 
                                                            class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                                            style="color: var(--text-primary);">
                                                        <i class="fas fa-redo mr-2" style="color: var(--primary);"></i> Resend
                                                    </button>
                                                    @if(($email->status ?? '') === 'failed' && $email->metadata)
                                                        <button onclick="viewError('{{ $email->id }}')" 
                                                                class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                                                style="color: var(--text-primary);">
                                                            <i class="fas fa-bug mr-2" style="color: var(--warning);"></i> View Error
                                                        </button>
                                                    @endif
                                                    <button onclick="copyEmail('{{ $email->id }}')" 
                                                            class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                                            style="color: var(--text-primary);">
                                                        <i class="fas fa-copy mr-2" style="color: var(--secondary);"></i> Copy to Draft
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

<!-- Modals (same as before) -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70" onclick="hideDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="rounded-lg shadow-xl w-full max-w-md" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center p-5 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-trash mr-2" style="color: var(--danger);"></i> Delete Sent Email
                </h3>
                <button type="button" onclick="hideDeleteModal()" class="transition" style="color: var(--text-secondary);">
                    <i class="fas fa-times hover:text-red-500"></i>
                </button>
            </div>
            <div class="p-5">
                <p class="text-sm" id="deleteMessage" style="color: var(--text-secondary);">
                    Are you sure you want to delete this sent email? This action cannot be undone.
                </p>
                <div class="mt-3 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                    <p class="text-xs" style="color: var(--warning);">
                        <i class="fas fa-info-circle mr-1"></i>
                        This will only remove the email from your dashboard.
                    </p>
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
                    <i class="fas fa-trash mr-2"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<div id="errorModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70" onclick="hideErrorModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="rounded-lg shadow-xl w-full max-w-2xl" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center p-5 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-bug mr-2" style="color: var(--warning);"></i> Email Error Details
                </h3>
                <button type="button" onclick="hideErrorModal()" class="transition" style="color: var(--text-secondary);">
                    <i class="fas fa-times hover:text-red-500"></i>
                </button>
            </div>
            <div class="p-5">
                <div class="mb-4">
                    <p class="text-sm font-medium" style="color: var(--text-primary);">Error Message</p>
                    <div class="mt-1 p-3 rounded-lg text-sm font-mono" style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2); color: var(--danger);">
                        <span id="errorMessage"></span>
                    </div>
                </div>
                <div class="mb-4">
                    <p class="text-sm font-medium" style="color: var(--text-primary);">Subject</p>
                    <p class="text-sm mt-1" id="errorSubject" style="color: var(--text-secondary);"></p>
                </div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--text-primary);">Recipient</p>
                    <p class="text-sm mt-1" id="errorRecipient" style="color: var(--text-secondary);"></p>
                </div>
            </div>
            <div class="flex justify-end gap-3 p-5 border-t" style="border-color: var(--border-color);">
                <button type="button" onclick="hideErrorModal()" 
                        class="px-4 py-2 rounded-lg font-medium transition"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                    Close
                </button>
                <button type="button" onclick="resendFromError()" 
                        class="px-4 py-2 rounded-lg font-medium text-white transition hover:opacity-90"
                        style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                    <i class="fas fa-redo mr-2"></i> Resend
                </button>
            </div>
        </div>
    </div>
</div>

<div id="resendModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70" onclick="hideResendModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="rounded-lg shadow-xl w-full max-w-md" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center p-5 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-redo mr-2" style="color: var(--primary);"></i> Resend Email
                </h3>
                <button type="button" onclick="hideResendModal()" class="transition" style="color: var(--text-secondary);">
                    <i class="fas fa-times hover:text-red-500"></i>
                </button>
            </div>
            <div class="p-5">
                <p class="text-sm" style="color: var(--text-secondary);">
                    Are you sure you want to resend this email to <span id="resendRecipient" class="font-medium" style="color: var(--text-primary);"></span>?
                </p>
            </div>
            <div class="flex justify-end gap-3 p-5 border-t" style="border-color: var(--border-color);">
                <button type="button" onclick="hideResendModal()" 
                        class="px-4 py-2 rounded-lg font-medium transition"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                    Cancel
                </button>
                <button type="button" id="confirmResendBtn" 
                        class="px-4 py-2 rounded-lg font-medium text-white transition hover:opacity-90"
                        style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                    <i class="fas fa-redo mr-2"></i> Resend
                </button>
            </div>
        </div>
    </div>
</div>

<div id="syncOverlay" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="rounded-lg shadow-xl p-8 text-center" style="background-color: var(--card-bg);">
            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4"
                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                <i class="fas fa-paper-plane fa-spin text-2xl text-white"></i>
            </div>
            <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">Processing</h4>
            <p class="text-sm" style="color: var(--text-secondary);">Please wait while we process your request...</p>
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

let deleteEmailId = null;
let resendEmailId = null;
let selectedEmails = new Set();

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

function resendSelected() {
    const ids = getSelectedIds();
    if (ids.length === 0) {
        showNotification('Please select at least one email', 'warning');
        return;
    }
    
    if (!confirm(`Resend ${ids.length} selected email(s)?`)) return;
    
    const overlay = document.getElementById('syncOverlay');
    overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    let completed = 0;
    ids.forEach(id => {
        fetch(`${baseUrl}/${accountId}/emails/${id}/resend`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            completed++;
            if (completed === ids.length) {
                overlay.classList.add('hidden');
                document.body.style.overflow = 'auto';
                showNotification(`${ids.length} email(s) resent successfully`, 'success');
                setTimeout(() => window.location.reload(), 1500);
            }
        })
        .catch(error => {
            completed++;
            if (completed === ids.length) {
                overlay.classList.add('hidden');
                document.body.style.overflow = 'auto';
                showNotification(`Resent ${completed - 1}/${ids.length} emails`, 'warning');
                setTimeout(() => window.location.reload(), 2000);
            }
        });
    });
}

function deleteSelected() {
    const ids = getSelectedIds();
    if (ids.length === 0) {
        showNotification('Please select at least one email', 'warning');
        return;
    }
    
    if (!confirm(`Delete ${ids.length} selected email(s)?`)) return;
    
    const overlay = document.getElementById('syncOverlay');
    overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    let completed = 0;
    ids.forEach(id => {
        fetch(`${baseUrl}/${accountId}/emails/${id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            completed++;
            if (completed === ids.length) {
                overlay.classList.add('hidden');
                document.body.style.overflow = 'auto';
                showNotification(`${ids.length} email(s) deleted`, 'success');
                setTimeout(() => window.location.reload(), 1500);
            }
        })
        .catch(error => {
            completed++;
            if (completed === ids.length) {
                overlay.classList.add('hidden');
                document.body.style.overflow = 'auto';
                showNotification(`Deleted ${completed - 1}/${ids.length} emails`, 'warning');
                setTimeout(() => window.location.reload(), 2000);
            }
        });
    });
}

// ========================================== //
// 📝 SINGLE EMAIL ACTIONS                    //
// ========================================== //

function resendEmail(emailId) {
    resendEmailId = emailId;
    
    const item = document.querySelector(`.email-item[data-email-id="${emailId}"]`);
    if (item) {
        const recipient = item.querySelector('.text-sm.font-medium')?.textContent?.replace('To: ', '') || 'recipient';
        document.getElementById('resendRecipient').textContent = recipient;
    }
    
    const modal = document.getElementById('resendModal');
    const confirmBtn = document.getElementById('confirmResendBtn');
    confirmBtn.onclick = function() {
        executeResend(emailId);
    };
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function executeResend(emailId) {
    hideResendModal();
    
    const overlay = document.getElementById('syncOverlay');
    overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    fetch(`${baseUrl}/${accountId}/emails/${emailId}/resend`, {
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
            showNotification('Email resent successfully!', 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification(data.message || 'Failed to resend email', 'error');
        }
    })
    .catch(error => {
        overlay.classList.add('hidden');
        document.body.style.overflow = 'auto';
        showNotification('An error occurred', 'error');
        console.error('Resend error:', error);
    });
}

function deleteEmail(emailId) {
    deleteEmailId = emailId;
    
    const modal = document.getElementById('deleteModal');
    const message = document.getElementById('deleteMessage');
    message.textContent = 'Are you sure you want to delete this sent email? This action cannot be undone.';
    
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    confirmBtn.onclick = function() {
        executeDelete(emailId);
    };
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function executeDelete(emailId) {
    hideDeleteModal();
    
    const overlay = document.getElementById('syncOverlay');
    overlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    fetch(`${baseUrl}/${accountId}/emails/${emailId}`, {
        method: 'DELETE',
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
            showNotification('Email deleted successfully', 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification(data.message || 'Failed to delete email', 'error');
        }
    })
    .catch(error => {
        overlay.classList.add('hidden');
        document.body.style.overflow = 'auto';
        showNotification('An error occurred', 'error');
        console.error('Delete error:', error);
    });
}

function viewError(emailId) {
    const item = document.querySelector(`.email-item[data-email-id="${emailId}"]`);
    if (!item) return;
    
    const errorText = item.querySelector('.text-xs.text-danger')?.textContent?.trim() || 'Unknown error';
    const subject = item.querySelector('.text-sm .font-medium')?.textContent || '(No Subject)';
    const recipient = item.querySelector('.text-sm.font-medium')?.textContent?.replace('To: ', '') || 'Unknown';
    
    document.getElementById('errorMessage').textContent = errorText;
    document.getElementById('errorSubject').textContent = subject;
    document.getElementById('errorRecipient').textContent = recipient;
    
    document.getElementById('errorModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function copyEmail(emailId) {
    showNotification('Copying to draft...', 'info');
    
    fetch(`${baseUrl}/${accountId}/emails/${emailId}/copy`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Email copied to drafts!', 'success');
            setTimeout(() => {
                window.location.href = '{{ route($routeNamePrefix . '.compose') }}';
            }, 1500);
        } else {
            showNotification(data.message || 'Failed to copy email', 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred', 'error');
        console.error('Copy error:', error);
    });
}

// ========================================== //
// 🔍 FILTER EMAILS                          //
// ========================================== //

function filterEmails(status) {
    if (status === 'all') {
        window.location.href = '{{ route($routeNamePrefix . '.sent') }}';
    } else {
        window.location.href = `{{ route($routeNamePrefix . '.sent') }}?status=${status}`;
    }
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

document.addEventListener('click', function(e) {
    if (!e.target.closest('.relative')) {
        document.querySelectorAll('.dropdown-menu').forEach(d => d.classList.add('hidden'));
    }
});

// ========================================== //
// 📋 MODAL CONTROLS                          //
// ========================================== //

function hideDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function hideErrorModal() {
    document.getElementById('errorModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function hideResendModal() {
    document.getElementById('resendModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function resendFromError() {
    const emailId = deleteEmailId || resendEmailId;
    if (emailId) {
        hideErrorModal();
        resendEmail(emailId);
    }
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

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideDeleteModal();
        hideErrorModal();
        hideResendModal();
    }
    
    if (e.ctrlKey && e.key === 'a') {
        e.preventDefault();
        selectAll();
    }
});

// ========================================== //
// 🎨 CSS STYLES                              //
// ========================================== //

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
    
    .email-item {
        transition: background-color 0.2s ease;
    }
    
    .email-item:hover {
        background-color: rgba(var(--secondary-rgb), 0.05);
    }
    
    .email-item .dropdown-menu {
        animation: fadeIn 0.15s ease-out;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    .badge-success {
        background-color: rgba(var(--success-rgb), 0.1);
        color: var(--success);
    }
    
    .badge-danger {
        background-color: rgba(var(--danger-rgb), 0.1);
        color: var(--danger);
    }
    
    .badge-warning {
        background-color: rgba(var(--warning-rgb), 0.1);
        color: var(--warning);
    }
    
    .badge-info {
        background-color: rgba(var(--info-rgb), 0.1);
        color: var(--info);
    }
    
    .badge-primary {
        background-color: rgba(var(--primary-rgb), 0.1);
        color: var(--primary);
    }
    
    .badge-secondary {
        background-color: rgba(var(--secondary-rgb), 0.1);
        color: var(--secondary);
    }
`;
document.head.appendChild(style);

console.log('✅ Sent Emails Page Loaded');
console.log('Account ID:', accountId);
console.log('Sent Count:', {{ $sentCount }});
</script>
@endsection