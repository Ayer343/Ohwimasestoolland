<!-- Overlay for mobile -->
<div class="overlay" id="overlay"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <!-- Enhanced Professional Logo Section -->
    <div class="logo-section">
        @php
            // Get system settings
            $systemSettings = \App\Models\SystemSetting::getSettings();
            $systemLogo = $systemSettings->system_logo ?? null;
            $systemShortName = $systemSettings->system_short_name ?? config('app.short_name', 'Tenant');
            $systemName = $systemSettings->system_name ?? config('app.name', 'Laravel');
            
            // FIX: Ensure sidebarUnreadCount is always defined
            if (!isset($sidebarUnreadCount)) {
                $sidebarUnreadCount = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0;
            }
            
            // Get email accounts for tenant
            $user = auth()->user();
            $emailAccounts = $user ? $user->emailAccounts()->get() : collect();
            $pendingEmailCount = $emailAccounts->where('status', 'pending')->count();
            $failedEmailCount = $emailAccounts->where('status', 'failed')->count();
            $totalEmailIssues = $pendingEmailCount + $failedEmailCount;
            
            $totalUnreadEmails = 0;
            foreach ($emailAccounts as $account) {
                try {
                    $totalUnreadEmails += $account->emails()->where('is_read', false)->where('folder', 'INBOX')->count();
                } catch (\Exception $e) {
                    // Skip if relationship fails
                }
            }
        @endphp
        
        <div class="logo-container">
            <div class="logo-wrapper">
                @if($systemLogo)
                    <!-- Professional logo display with proper error handling -->
                    <div class="logo-image-container">
                        <img src="{{ Storage::url($systemLogo) }}" 
                             alt="{{ $systemName }}" 
                             class="logo-image"
                             onerror="this.style.display='none'; document.getElementById('logoFallback').style.display='flex';">
                        <!-- Professional fallback - initially hidden -->
                        <div id="logoFallback" class="logo-fallback" style="display: none;">
                            <i class="fas fa-home"></i>
                        </div>
                    </div>
                @else
                    <!-- Professional default logo when no system logo exists -->
                    <div class="logo-default">
                        <i class="fas fa-home"></i>
                    </div>
                @endif
            </div>
            
            <div class="logo-content">
                <div class="logo-text-container">
                    <div class="logo-shortname" style="color: var(--sidebar-text);" title="{{ $systemName }}">
                        {{ $systemShortName }}
                    </div>
                    @if($systemName && $systemName !== $systemShortName)
                    <div class="logo-fullname" style="color: var(--sidebar-text); opacity: 0.8;">
                        {{ $systemName }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
        
        <div class="toggle-sidebar" id="toggleSidebarDesktop">
            <i class="fas fa-chevron-left"></i>
        </div>
    </div>
    
    <!-- Professional separator -->
    <div class="logo-separator"></div>
    
    <!-- Compact navigation with minimal gap -->
    <nav class="mt-2">
        <div class="nav-divider">
            <span class="menu-text">MAIN NAVIGATION</span>
        </div>
        
        <a href="{{ route('tenant.dashboard') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('tenant.dashboard') ? 'active' : '' }}">
            <i class="fas fa-home mr-4"></i>
            <span class="nav-text">Dashboard</span>
        </a>
        
        <!-- My Unit Link -->
        <a href="{{ route('tenant.property-units.my-unit') }}" class="nav-item flex items-center py-2 px-6">
            <i class="fas fa-building mr-4"></i>
            <span class="nav-text">My Unit</span>
        </a>
        
        <!-- My Invoices Link -->
        <a href="{{ route('tenant.invoices.index') }}" class="nav-item flex items-center py-2 px-6">
            <i class="fas fa-file-invoice mr-4"></i>
            <span class="nav-text">My Invoices</span>
        </a>
        
        <!-- Payment History -->
        <a href="#" class="nav-item flex items-center py-2 px-6">
            <i class="fas fa-credit-card mr-4"></i>
            <span class="nav-text">Payment History</span>
        </a>
        
        <!-- Maintenance Requests - Using tenant routes -->
        <a href="{{ route('tenant.maintenance.index') }}" 
           class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('tenant.maintenance*') ? 'active' : '' }}">
            <i class="fas fa-tools mr-4"></i>
            <span class="nav-text">Maintenance</span>
            @php
                $pendingCount = \App\Models\MaintenanceRequest::where('unit_id', auth()->user()->tenantUnit->id ?? 0)
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->count();
            @endphp
            @if($pendingCount > 0)
                <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">
                    {{ $pendingCount }}
                </span>
            @endif
        </a>
        
        <!-- ============================================ -->
        <!-- 💬 COMMUNICATION SECTION - NEW -->
        <!-- ============================================ -->
        <div class="nav-divider mt-4">
            <span class="menu-text">COMMUNICATION</span>
        </div>

        <!-- ✅ Email Management Button -->
        <button id="emailManagementBtn" class="nav-item flex items-center py-2 px-6 w-full text-left hover:bg-opacity-20 transition-colors duration-200 {{ request()->routeIs('email-accounts.*') ? 'active' : '' }}" style="color: var(--sidebar-text);">
            <i class="fas fa-envelope mr-4"></i>
            <span class="nav-text flex-grow">Email Management</span>
            @if($totalEmailIssues > 0)
                <span class="ml-auto bg-yellow-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                    {{ $totalEmailIssues > 9 ? '9+' : $totalEmailIssues }}
                </span>
            @elseif($totalUnreadEmails > 0)
                <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                    {{ $totalUnreadEmails > 9 ? '9+' : $totalUnreadEmails }}
                </span>
            @else
                <i class="fas fa-chevron-right ml-auto text-xs opacity-70"></i>
            @endif
        </button>
        
        <!-- Minimal spacing between sections -->
        <div class="nav-divider mt-1">
            <span class="menu-text">ACCOUNT</span>
        </div>
        
        <!-- Profile Settings Link -->
        <a href="{{ route('tenant.profile.edit') }}" class="nav-item flex items-center py-2 px-6">
            <i class="fas fa-user-cog mr-4"></i>
            <span class="nav-text">Profile Settings</span>
        </a>
        
        <!-- ✅ UPDATED: Notifications link with live count -->
        <a href="{{ route('tenant.notifications.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('*.notifications.*') ? 'active' : '' }}" id="sidebarNotificationsLink">
            <i class="fas fa-bell mr-4"></i>
            <span class="nav-text">Notifications</span>
            <span class="ml-auto" id="sidebarNotificationBadge" style="display: none;">
                <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full"></span>
            </span>
        </a>
        
        <!-- Minimal spacing between sections -->
        <div class="nav-divider mt-1">
            <span class="menu-text">SUPPORT</span>
        </div>
        
        <!-- Help & Support Link -->
        <a href="#" class="nav-item flex items-center py-2 px-6">
            <i class="fas fa-question-circle mr-4"></i>
            <span class="nav-text">Help & Support</span>
        </a>
        
        <!-- Contact Management -->
        <a href="#" class="nav-item flex items-center py-2 px-6">
            <i class="fas fa-envelope mr-4"></i>
            <span class="nav-text">Contact Management</span>
        </a>
    </nav>
    
    <!-- Settings Button - Compact positioning -->
    <div class="absolute bottom-0 w-full p-3">
        <button id="themeSettingsButton" class="nav-item flex items-center py-2 px-6 w-full justify-center" title="Theme Settings">
            <i class="fas fa-cog text-xl theme-settings-gear" id="settingsIcon"></i>
        </button>
    </div>
</div>

<!-- ============ EMAIL ACCOUNT MANAGEMENT MODAL ============ -->
@if(auth()->check())
<div id="emailManagementModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden overflow-y-auto" style="padding-top: 2rem; padding-bottom: 2rem;">
    <div class="email-modal relative mx-auto my-auto" 
         style="background-color: var(--card-bg); 
                border: 1px solid var(--border-color);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: modalSlideUp 0.3s ease-out;
                width: 95%;
                max-width: 1400px;
                max-height: calc(100vh - 4rem);
                display: flex;
                flex-direction: column;
                border-radius: 16px;
                overflow: hidden;">
        
        <!-- Modal Header -->
        <div class="modal-header flex justify-between items-center p-6 border-b flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--card-bg);">
            <div>
                <h3 class="text-xl font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-envelope mr-3" style="color: var(--primary);"></i>
                    Email Account Management
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Manage your email accounts and communication
                </p>
            </div>
            <button id="closeEmailModal" 
                    class="p-2 rounded-full transition-colors duration-200 hover:bg-opacity-20"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="modal-body p-6 overflow-y-auto" style="max-height: calc(100vh - 12rem); scroll-behavior: smooth;">
            
            {{-- Email Account Stats Summary --}}
            @php
                $user = auth()->user();
                $emailAccounts = $user ? $user->emailAccounts()->get() : collect();
                $totalAccounts = $emailAccounts->count();
                $verifiedAccounts = $emailAccounts->where('status', 'verified')->count();
                $pendingAccounts = $emailAccounts->where('status', 'pending')->count();
                $failedAccounts = $emailAccounts->where('status', 'failed')->count();
                $totalEmails = 0;
                $unreadEmails = 0;
                foreach ($emailAccounts as $account) {
                    try {
                        $totalEmails += $account->emails()->count();
                        $unreadEmails += $account->emails()->where('is_read', false)->where('folder', 'INBOX')->count();
                    } catch (\Exception $e) {
                        // Skip if relationship fails
                    }
                }
            @endphp
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 mb-6">
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $totalAccounts }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Total Accounts</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #22c55e;">{{ $verifiedAccounts }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Verified</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #eab308;">{{ $pendingAccounts }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Pending</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #ef4444;">{{ $failedAccounts }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Failed</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $totalEmails }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Total Emails</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #ef4444;">{{ $unreadEmails }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Unread</div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="mb-6">
                <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i>Quick Actions
                </h4>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    <a href="{{ route('email-accounts.create') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeEmailModal()">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, var(--primary), var(--secondary));">
                            <i class="fas fa-plus text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Link Account</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Add new email</div>
                        </div>
                    </a>
                    
                    <a href="{{ route('email-accounts.index') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeEmailModal()">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);">
                            <i class="fas fa-list text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">All Accounts</div>
                            <div class="text-xs" style="color: var(--text-secondary);">View all linked</div>
                        </div>
                    </a>
                    
                    <a href="{{ route('email-accounts.inbox') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeEmailModal()">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #22c55e, #4ade80);">
                            <i class="fas fa-inbox text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Inbox</div>
                            <div class="text-xs" style="color: var(--text-secondary);">
                                {{ $unreadEmails > 0 ? $unreadEmails . ' unread' : 'All read' }}
                            </div>
                        </div>
                    </a>
                    
                    <a href="{{ route('email-accounts.compose') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeEmailModal()">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                            <i class="fas fa-pen text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Compose</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Write new email</div>
                        </div>
                    </a>
                    
                    <a href="{{ route('email-accounts.sent') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeEmailModal()">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                            <i class="fas fa-paper-plane text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Sent</div>
                            <div class="text-xs" style="color: var(--text-secondary);">View sent emails</div>
                        </div>
                    </a>
                    
                    <a href="#" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="event.preventDefault(); syncAllEmails();">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #06b6d4, #22d3ee);">
                            <i class="fas fa-sync text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Sync All</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Fetch new emails</div>
                        </div>
                    </a>
                </div>
            </div>
            
            <!-- Email Accounts List -->
            @if($totalAccounts > 0)
                <div>
                    <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-list mr-2" style="color: var(--primary);"></i>
                        Your Email Accounts
                        <span class="text-xs ml-2 px-2 py-0.5 rounded-full" style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                            {{ $totalAccounts }}
                        </span>
                    </h4>
                    <div class="space-y-2">
                        @foreach($emailAccounts as $account)
                            @php
                                $accountUnread = 0;
                                try {
                                    $accountUnread = $account->emails()->where('is_read', false)->where('folder', 'INBOX')->count();
                                } catch (\Exception $e) {
                                    $accountUnread = 0;
                                }
                                $accountTotal = 0;
                                try {
                                    $accountTotal = $account->emails()->count();
                                } catch (\Exception $e) {
                                    $accountTotal = 0;
                                }
                            @endphp
                            <div class="account-item flex items-center justify-between p-3 rounded-lg transition-all duration-200 hover:bg-opacity-10"
                                 style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="flex items-center min-w-0 flex-1">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                                         style="background: linear-gradient(135deg, {{ $account->is_primary ? 'var(--primary)' : '#6b7280' }}, {{ $account->is_primary ? 'var(--secondary)' : '#9ca3af' }});">
                                        <i class="fas fa-envelope text-white text-xs"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center flex-wrap">
                                            <span class="text-sm font-medium truncate" style="color: var(--text-primary);">
                                                {{ $account->display_name ?? $account->email }}
                                            </span>
                                            @if($account->is_primary)
                                                <span class="ml-2 px-1.5 py-0.5 text-[10px] rounded-full" style="background-color: rgba(34, 197, 94, 0.2); color: #22c55e;">
                                                    <i class="fas fa-star mr-0.5"></i>Primary
                                                </span>
                                            @endif
                                            <span class="ml-2 px-1.5 py-0.5 text-[10px] rounded-full {{ $account->status === 'verified' ? 'bg-green-100 text-green-700' : ($account->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                                {{ ucfirst($account->status) }}
                                            </span>
                                        </div>
                                        <div class="text-xs truncate" style="color: var(--text-secondary);">
                                            {{ $account->email }}
                                            <span class="mx-1">•</span>
                                            {{ $account->provider }}
                                            @if($accountUnread > 0)
                                                <span class="ml-2 px-1.5 py-0.5 rounded-full text-[10px]" style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444;">
                                                    {{ $accountUnread }} unread
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-1 flex-shrink-0 ml-2">
                                    @if(!$account->is_primary && $account->status === 'verified')
                                        <button onclick="setPrimaryAccount('{{ $account->id }}')" 
                                                class="p-1.5 rounded transition-colors duration-200 hover:bg-opacity-20"
                                                style="color: var(--text-secondary);"
                                                title="Set as primary">
                                            <i class="fas fa-star text-xs"></i>
                                        </button>
                                    @endif
                                    <a href="{{ route('email-accounts.show', $account) }}" 
                                       class="p-1.5 rounded transition-colors duration-200 hover:bg-opacity-20"
                                       style="color: var(--text-secondary);"
                                       onclick="closeEmailModal()"
                                       title="View account">
                                        <i class="fas fa-eye text-xs"></i>
                                    </a>
                                    <a href="{{ route('email-accounts.edit', $account) }}" 
                                       class="p-1.5 rounded transition-colors duration-200 hover:bg-opacity-20"
                                       style="color: var(--text-secondary);"
                                       onclick="closeEmailModal()"
                                       title="Edit account">
                                        <i class="fas fa-edit text-xs"></i>
                                    </a>
                                    <button onclick="syncAccount('{{ $account->id }}')" 
                                            class="p-1.5 rounded transition-colors duration-200 hover:bg-opacity-20"
                                            style="color: var(--text-secondary);"
                                            title="Sync emails">
                                        <i class="fas fa-sync text-xs"></i>
                                    </button>
                                    <button onclick="deleteAccount('{{ $account->id }}', '{{ $account->email }}')" 
                                            class="p-1.5 rounded transition-colors duration-200 hover:bg-opacity-20"
                                            style="color: var(--text-secondary);"
                                            title="Delete account">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="text-center py-8">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-envelope-open text-2xl" style="color: var(--primary);"></i>
                    </div>
                    <h4 class="text-lg font-medium mb-2" style="color: var(--text-primary);">No Email Accounts Linked</h4>
                    <p class="text-sm mb-4" style="color: var(--text-secondary);">
                        You haven't linked any email accounts yet.
                    </p>
                    <a href="{{ route('email-accounts.create') }}" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200"
                       style="background-color: var(--primary); color: white;"
                       onclick="closeEmailModal()">
                        <i class="fas fa-plus mr-2"></i>Link Your First Account
                    </a>
                </div>
            @endif
        </div>
        
        <!-- Modal Footer -->
        <div class="modal-footer p-4 border-t flex justify-between flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--bg-secondary);">
            <div>
                <a href="{{ route('email-accounts.index') }}" 
                   class="text-sm px-3 py-1.5 rounded-lg transition-colors duration-200"
                   style="background-color: var(--primary); color: white;"
                   onclick="closeEmailModal()">
                    <i class="fas fa-arrow-right mr-1"></i> Manage All Accounts
                </a>
            </div>
            <button id="cancelEmailModal" 
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200"
                    style="color: var(--text-secondary);">
                Close
            </button>
        </div>
    </div>
</div>
@endif

<!-- Theme Settings Modal -->
<div id="themeSettingsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="theme-modal-compact">
        <!-- Modal Header -->
        <div class="theme-modal-header-compact flex justify-between items-center">
            <h3 class="text-base font-semibold">
                <i class="fas fa-palette mr-2"></i>Theme Settings
            </h3>
            <button id="closeThemeModal" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="theme-modal-body-compact">
            <!-- Appearance Selection -->
            <div class="mb-6">
                <h4 class="section-header-compact">Appearance</h4>
                <p class="section-description-compact">Choose how the application looks</p>
                
                <div class="appearance-grid-compact">
                    <div class="appearance-option-compact relative" data-theme="light">
                        <div class="appearance-icon-compact bg-white border border-gray-300">
                            <i class="fas fa-sun text-yellow-500"></i>
                        </div>
                        <span class="appearance-label-compact">Light</span>
                    </div>
                    
                    <div class="appearance-option-compact relative" data-theme="dark">
                        <div class="appearance-icon-compact bg-gray-800 border border-gray-700">
                            <i class="fas fa-moon text-blue-300"></i>
                        </div>
                        <span class="appearance-label-compact">Dark</span>
                    </div>
                    
                    <div class="appearance-option-compact relative" data-theme="system">
                        <div class="appearance-icon-compact bg-gradient-to-r from-gray-100 to-gray-800 border border-gray-300">
                            <i class="fas fa-desktop text-gray-600"></i>
                        </div>
                        <span class="appearance-label-compact">System</span>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar Themes -->
            <div class="mb-4">
                <h4 class="section-header-compact">Sidebar Theme</h4>
                <p class="section-description-compact">Customize your sidebar appearance</p>
                
                <div class="theme-grid-compact">
                    <div class="theme-option-compact relative" data-theme="default">
                        <div class="theme-preview-compact bg-gradient-to-br from-purple-500 to-purple-600"></div>
                        <span class="theme-label-compact">Default</span>
                    </div>
                    
                    <div class="theme-option-compact relative" data-theme="dark">
                        <div class="theme-preview-compact bg-gradient-to-br from-gray-800 to-gray-900"></div>
                        <span class="theme-label-compact">Dark</span>
                    </div>
                    
                    <div class="theme-option-compact relative" data-theme="light">
                        <div class="theme-preview-compact bg-gradient-to-br from-white to-gray-100 border border-gray-200"></div>
                        <span class="theme-label-compact">Light</span>
                    </div>
                    
                    <div class="theme-option-compact relative" data-theme="blue">
                        <div class="theme-preview-compact bg-gradient-to-br from-blue-600 to-blue-800"></div>
                        <span class="theme-label-compact">Blue</span>
                    </div>
                    
                    <div class="theme-option-compact relative" data-theme="green">
                        <div class="theme-preview-compact bg-gradient-to-br from-green-600 to-green-800"></div>
                        <span class="theme-label-compact">Green</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="theme-modal-footer-compact flex justify-end">
            <button id="closeThemeModalBtn" class="px-3 py-1.5 text-sm font-medium transition-colors duration-200">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Search Modal -->
<div id="searchModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden pt-20">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-11/12 md:w-2/3 lg:w-1/2 max-w-2xl">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b dark:border-gray-700 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                <i class="fas fa-search mr-2"></i>Tenant Search
            </h3>
            <button id="closeSearchModal" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6">
            <!-- Search Input -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Search Term
                </label>
                <input type="text" id="globalSearchInput" placeholder="Search invoices, maintenance requests..." 
                       class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
            </div>
            
            <!-- Search Category -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Search Category
                </label>
                <select id="searchCategory" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    <option value="all">All Categories</option>
                    <option value="invoices">Invoices</option>
                    <option value="payments">Payments</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="notifications">Notifications</option>
                </select>
            </div>
            
            <!-- Search Filters -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Search Filters
                </label>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" id="filterExactMatch" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Exact Match</span>
                        </label>
                    </div>
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" id="filterCaseSensitive" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Case Sensitive</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Recent Searches -->
            <div id="recentSearches" class="hidden">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Recent Searches
                </label>
                <div id="recentSearchesList" class="flex flex-wrap gap-2">
                    <!-- Recent searches will be populated here -->
                </div>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="px-6 py-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-700 flex justify-between">
            <button id="clearSearch" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors duration-200">
                Clear
            </button>
            <div class="flex space-x-3">
                <button id="cancelSearch" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition-colors duration-200">
                    Cancel
                </button>
                <button id="performSearch" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors duration-200">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Notifications Dropdown Panel -->
<div id="notificationsDropdown" class="notifications-dropdown hidden">
    <div class="notifications-header">
        <h3>Notifications</h3>
        <div class="notifications-actions">
            <button id="markAllReadBtn" class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400">
                Mark all as read
            </button>
            <button id="clearAllNotificationsBtn" class="text-xs text-red-600 hover:text-red-800 dark:text-red-400">
                Clear all
            </button>
        </div>
    </div>
    <div id="notificationsList" class="notifications-list">
        <div class="notifications-loading">
            <i class="fas fa-spinner fa-spin"></i> Loading...
        </div>
    </div>
    <div class="notifications-footer">
        <a href="{{ route('tenant.notifications.index') }}" class="view-all-link">
            View all notifications →
        </a>
    </div>
</div>

<!-- Main Content Header -->
<div class="content" id="mainContent">
    <!-- Top Navigation -->
    <header class="header flex items-center justify-between">
        <div class="flex items-center">
            <button id="toggleSidebarMobile" class="mobile-menu-btn mr-4 text-gray-600">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">@yield('title', 'Tenant Dashboard')</h2>
        </div>
        
        <div class="flex items-center space-x-4">
            <!-- Enhanced Search Bar -->
            <div class="relative">
                <div class="flex items-center space-x-2">
                    <!-- Quick Search Input -->
                    <div class="relative hidden md:block">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--text-secondary);"></i>
                        <input type="text" id="quickSearchInput" placeholder="Search invoices or requests..." 
                               class="header-search pl-10 pr-10" 
                               style="color: var(--text-primary); background-color: var(--bg-secondary); border: 1px solid transparent;"
                               data-toggle="tooltip" title="Press / to focus">
                        <!-- Advanced Search Button - Now opens modal on click -->
                        <button id="advancedSearchBtn" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1 rounded">
                            <i class="fas fa-sliders-h text-sm"></i>
                        </button>
                    </div>
                    
                    <!-- Mobile Search Button -->
                    <button id="mobileSearchBtn" class="md:hidden p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700">
                        <i class="fas fa-search text-gray-600 dark:text-gray-300"></i>
                    </button>
                </div>
            </div>
            
            <!-- ✅ UPDATED: Notification Bell with Live Counter -->
            <div class="header-buttons flex items-center space-x-2">
                <div class="relative">
                    <button id="notificationBellBtn" class="relative p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="far fa-bell text-gray-600 dark:text-gray-300 text-xl"></i>
                        <span id="notificationBadge" class="notification-badge hidden">
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-bold">0</span>
                        </span>
                    </button>
                </div>
                
                <button class="relative p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700">
                    <i class="far fa-envelope text-gray-600 dark:text-gray-300"></i>
                    <span class="notification-dot"></span>
                </button>
            </div>
            
            <div class="dropdown relative">
                <button id="userMenuButton" class="flex items-center space-x-2">
                    <!-- Updated Profile Avatar with reduced border -->
                    <div class="avatar-minimal">
                        @if(Auth::user()->photo)
                            <img src="{{ Storage::url('public/users/photos/' . Auth::user()->photo) }}" alt="{{ Auth::user()->name }}" class="w-8 h-8 rounded-full object-cover">
                        @else
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-sm font-semibold">
                                {{ substr(Auth::user()->name, 0, 2) }}
                            </div>
                        @endif
                    </div>
                    <!-- Removed username text -->
                    <i class="fas fa-chevron-down text-xs" style="color: var(--text-secondary);"></i>
                </button>
                
                <div id="userDropdown" class="dropdown-menu" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                    <!-- Updated Profile Link -->
                    <a href="{{ route('tenant.profile.edit') }}" class="dropdown-item">
                        <i class="far fa-user mr-3" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-primary);">My Profile</span>
                    </a>
                    
                    <div class="border-t my-1" style="border-color: var(--border-color);"></div>
                    
                    <!-- ✅ UPDATED: Notifications with live count -->
                    <a href="{{ route('tenant.notifications.index') }}" class="dropdown-item" id="dropdownNotificationsLink">
                        <i class="far fa-bell mr-3" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-primary);">Notifications</span>
                        <span id="dropdownNotificationBadge" class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full hidden">0</span>
                    </a>
                    
                    <div class="border-t my-1" style="border-color: var(--border-color);"></div>
                    
                    <!-- Logout -->
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item w-full text-left text-red-500">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            <span>Logout</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="p-6">

<style>
/* Enhanced Minimal Avatar Styling */
.avatar-minimal {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: white;
    cursor: pointer;
    transition: all 0.3s;
    border: 1px solid var(--border-color);
}

.avatar-minimal:hover {
    transform: scale(1.05);
    border-color: var(--primary);
}

.avatar-minimal img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid var(--border-color);
}

/* Enhanced header spacing for cleaner look */
.header {
    padding: 12px 24px;
}

/* Search bar enhancements */
.header-search {
    min-width: 280px;
}

/* Mobile search button styling */
#mobileSearchBtn {
    padding: 8px;
}

/* Header buttons spacing adjustment */
.header-buttons {
    gap: 8px;
}

/* Dropdown menu positioning adjustment */
.dropdown-menu {
    min-width: 220px;
}

/* Quick search input focus state */
.header-search:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.1);
}

/* Advanced search button hover effect */
#advancedSearchBtn:hover {
    background-color: rgba(0, 0, 0, 0.05);
    transform: scale(1.1);
}

/* ✅ ADDED: Notifications Dropdown Styles */
.notifications-dropdown {
    position: absolute;
    top: 60px;
    right: 20px;
    width: 380px;
    max-width: calc(100vw - 40px);
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    border: 1px solid var(--border-color);
    overflow: hidden;
}

.notifications-dropdown.hidden {
    display: none;
}

.notifications-header {
    padding: 16px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notifications-header h3 {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.notifications-actions {
    display: flex;
    gap: 12px;
}

.notifications-actions button {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
}

.notifications-list {
    max-height: 400px;
    overflow-y: auto;
}

.notification-item {
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-color);
    transition: background-color 0.2s;
    cursor: pointer;
}

.notification-item:hover {
    background-color: var(--bg-secondary);
}

.notification-item.unread {
    background-color: rgba(59, 130, 246, 0.05);
}

.notification-item.unread:hover {
    background-color: rgba(59, 130, 246, 0.1);
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-secondary);
}

.notification-content {
    flex: 1;
}

.notification-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.notification-message {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.4;
}

.notification-time {
    font-size: 11px;
    color: var(--text-secondary);
    margin-top: 4px;
}

.notifications-loading {
    padding: 32px;
    text-align: center;
    color: var(--text-secondary);
}

.notifications-empty {
    padding: 48px 32px;
    text-align: center;
    color: var(--text-secondary);
}

.notifications-empty i {
    font-size: 48px;
    margin-bottom: 12px;
    opacity: 0.5;
}

.notifications-footer {
    padding: 12px 16px;
    border-top: 1px solid var(--border-color);
    text-align: center;
}

.view-all-link {
    font-size: 13px;
    color: #3b82f6;
    text-decoration: none;
    font-weight: 500;
}

.view-all-link:hover {
    text-decoration: underline;
}

.notification-badge {
    position: relative;
}

/* Mobile responsiveness adjustments */
@media (max-width: 768px) {
    .header {
        padding: 10px 16px;
    }
    
    .avatar-minimal {
        width: 28px;
        height: 28px;
    }
    
    .header-buttons {
        gap: 6px;
    }
    
    .notifications-dropdown {
        position: fixed;
        top: 60px;
        right: 10px;
        left: 10px;
        width: auto;
        max-width: none;
    }
}

/* Dark mode adjustments for minimal avatar */
[data-theme="dark"] .avatar-minimal {
    border-color: #4b5563;
}

[data-theme="dark"] .avatar-minimal:hover {
    border-color: var(--primary);
}

/* Smooth transitions for all interactive elements */
.avatar-minimal,
#advancedSearchBtn,
#mobileSearchBtn {
    transition: all 0.2s ease-in-out;
}

/* Theme Settings Modal Compact Styles */
.theme-modal-compact {
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    width: 90%;
    max-width: 400px;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalFadeIn 0.3s ease-out;
}

.theme-modal-header-compact {
    padding: 16px 20px;
    border-bottom: 1px solid #e5e7eb;
}

.theme-modal-body-compact {
    padding: 20px;
}

.theme-modal-footer-compact {
    padding: 16px 20px;
    border-top: 1px solid #e5e7eb;
}

.section-header-compact {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 4px;
}

.section-description-compact {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 16px;
}

.appearance-grid-compact {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.appearance-option-compact {
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    padding: 12px 8px;
    border-radius: 8px;
    transition: all 0.2s ease;
    border: 2px solid transparent;
    position: relative;
}

.appearance-option-compact:hover {
    background-color: #f9fafb;
    transform: translateY(-2px);
}

.appearance-option-compact.active {
    border-color: var(--primary);
    background-color: rgba(var(--primary-rgb), 0.08);
}

.appearance-icon-compact {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 8px;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.appearance-label-compact {
    font-size: 12px;
    font-weight: 500;
    color: #374151;
}

.theme-grid-compact {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 8px;
}

.theme-option-compact {
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    padding: 8px 4px;
    border-radius: 6px;
    transition: all 0.2s ease;
    border: 2px solid transparent;
    position: relative;
}

.theme-option-compact:hover {
    background-color: #f9fafb;
    transform: translateY(-2px);
}

.theme-option-compact.active {
    border-color: var(--primary);
    background-color: rgba(var(--primary-rgb), 0.08);
}

.theme-preview-compact {
    width: 40px;
    height: 40px;
    border-radius: 6px;
    margin-bottom: 6px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.theme-label-compact {
    font-size: 11px;
    font-weight: 500;
    color: #374151;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(-10px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* Current selection indicator */
.current-selection {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 20px;
    height: 20px;
    background: var(--primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 10px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

/* Dark mode styles for modal */
[data-theme="dark"] .theme-modal-compact {
    background: #1f2937;
    color: white;
}

[data-theme="dark"] .theme-modal-header-compact,
[data-theme="dark"] .theme-modal-footer-compact {
    border-color: #374151;
}

[data-theme="dark"] .section-header-compact {
    color: #f9fafb;
}

[data-theme="dark"] .section-description-compact {
    color: #9ca3af;
}

[data-theme="dark"] .appearance-option-compact:hover {
    background-color: #374151;
}

[data-theme="dark"] .appearance-label-compact,
[data-theme="dark"] .theme-label-compact {
    color: #f9fafb;
}

[data-theme="dark"] .theme-option-compact:hover {
    background-color: #374151;
}

[data-theme="dark"] .appearance-option-compact.active,
[data-theme="dark"] .theme-option-compact.active {
    background-color: rgba(var(--primary-rgb), 0.2);
}

/* Tenant specific badge colors */
.bg-orange-500 {
    background-color: #f97316;
}

/* Email account item styles */
.account-item {
    transition: all 0.2s ease;
}

.account-item:hover {
    background-color: rgba(var(--primary-rgb), 0.05);
}

/* Modal animations */
@keyframes modalSlideUp {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============================================
    // NOTIFICATION SYSTEM
    // ============================================
    
    let unreadCount = 0;
    let updateInterval = null;
    
    // Get user role
    const userRole = 'tenant';
    
    // Notification functions
    async function fetchUnreadCount() {
        try {
            const response = await fetch('/api/notifications/count');
            const data = await response.json();
            unreadCount = data.unread_count || 0;
            updateNotificationBadges(unreadCount);
            return unreadCount;
        } catch (error) {
            console.error('Error fetching notification count:', error);
            return 0;
        }
    }
    
    async function fetchRecentNotifications(limit = 10) {
        try {
            const response = await fetch(`/api/notifications/recent?limit=${limit}`);
            const notifications = await response.json();
            renderNotificationsDropdown(notifications);
            return notifications;
        } catch (error) {
            console.error('Error fetching notifications:', error);
            return [];
        }
    }
    
    async function markNotificationAsRead(notificationId) {
        try {
            const response = await fetch(`/api/notifications/${notificationId}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            if (response.ok) {
                fetchUnreadCount();
                fetchRecentNotifications();
                return true;
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
        return false;
    }
    
    async function markAllAsRead() {
        try {
            const response = await fetch('/api/notifications/read-all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            if (response.ok) {
                fetchUnreadCount();
                fetchRecentNotifications();
                showToast('All notifications marked as read', 'success');
                return true;
            }
        } catch (error) {
            console.error('Error marking all as read:', error);
            showToast('Failed to mark all as read', 'error');
        }
        return false;
    }
    
    async function clearAllNotifications() {
        if (!confirm('Are you sure you want to clear all notifications? This action cannot be undone.')) {
            return;
        }
        
        try {
            const response = await fetch('/api/notifications', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            if (response.ok) {
                fetchUnreadCount();
                fetchRecentNotifications();
                showToast('All notifications cleared', 'success');
                return true;
            }
        } catch (error) {
            console.error('Error clearing notifications:', error);
            showToast('Failed to clear notifications', 'error');
        }
        return false;
    }
    
    function updateNotificationBadges(count) {
        // Update bell badge
        const badge = document.getElementById('notificationBadge');
        const badgeCount = badge?.querySelector('span');
        
        if (count > 0) {
            if (badgeCount) {
                badgeCount.textContent = count > 99 ? '99+' : count;
            }
            badge?.classList.remove('hidden');
            
            // Update sidebar badge
            const sidebarBadge = document.getElementById('sidebarNotificationBadge');
            if (sidebarBadge) {
                const badgeSpan = sidebarBadge.querySelector('span');
                if (badgeSpan) badgeSpan.textContent = count > 99 ? '99+' : count;
                sidebarBadge.style.display = 'block';
            }
            
            // Update dropdown badge
            const dropdownBadge = document.getElementById('dropdownNotificationBadge');
            if (dropdownBadge) {
                dropdownBadge.textContent = count > 99 ? '99+' : count;
                dropdownBadge.classList.remove('hidden');
            }
        } else {
            badge?.classList.add('hidden');
            const sidebarBadge = document.getElementById('sidebarNotificationBadge');
            if (sidebarBadge) sidebarBadge.style.display = 'none';
            
            const dropdownBadge = document.getElementById('dropdownNotificationBadge');
            if (dropdownBadge) dropdownBadge.classList.add('hidden');
        }
    }
    
    function renderNotificationsDropdown(notifications) {
        const container = document.getElementById('notificationsList');
        if (!container) return;
        
        if (!notifications || notifications.length === 0) {
            container.innerHTML = `
                <div class="notifications-empty">
                    <i class="far fa-bell-slash"></i>
                    <p>No notifications yet</p>
                    <p class="text-xs mt-2">When you receive notifications, they'll appear here</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = notifications.map(notification => `
            <div class="notification-item ${!notification.is_read ? 'unread' : ''}" data-id="${notification.id}">
                <div class="flex items-start space-x-3">
                    <div class="notification-icon">
                        <i class="${notification.icon || 'fas fa-bell'}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">${escapeHtml(notification.title)}</div>
                        <div class="notification-message">${escapeHtml(notification.message)}</div>
                        <div class="notification-time">${notification.time_ago || formatTimeAgo(notification.created_at)}</div>
                    </div>
                </div>
            </div>
        `).join('');
        
        // Add click handlers
        container.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', async (e) => {
                e.stopPropagation();
                const notificationId = item.dataset.id;
                const notification = notifications.find(n => n.id === notificationId);
                
                if (notification && !notification.is_read) {
                    await markNotificationAsRead(notificationId);
                }
                
                // Redirect if action URL exists
                if (notification?.action_url) {
                    window.location.href = notification.action_url;
                } else {
                    // Close dropdown and go to notifications page
                    document.getElementById('notificationsDropdown')?.classList.add('hidden');
                    window.location.href = `/${userRole}/notifications/${notificationId}`;
                }
            });
        });
    }
    
    function formatTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins} minute${diffMins === 1 ? '' : 's'} ago`;
        if (diffHours < 24) return `${diffHours} hour${diffHours === 1 ? '' : 's'} ago`;
        if (diffDays < 7) return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`;
        
        return date.toLocaleDateString();
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `fixed bottom-4 right-4 z-50 px-4 py-2 rounded-lg shadow-lg text-white ${
            type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500'
        }`;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    // Initialize notification system
    function initNotificationSystem() {
        fetchUnreadCount();
        fetchRecentNotifications();
        
        // Set up polling every 30 seconds
        if (updateInterval) clearInterval(updateInterval);
        updateInterval = setInterval(() => {
            fetchUnreadCount();
            const dropdown = document.getElementById('notificationsDropdown');
            if (dropdown && !dropdown.classList.contains('hidden')) {
                fetchRecentNotifications();
            }
        }, 30000);
    }
    
    // Notification dropdown toggle
    const notificationBell = document.getElementById('notificationBellBtn');
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    
    if (notificationBell && notificationsDropdown) {
        notificationBell.addEventListener('click', async (e) => {
            e.stopPropagation();
            notificationsDropdown.classList.toggle('hidden');
            if (!notificationsDropdown.classList.contains('hidden')) {
                await fetchRecentNotifications();
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!notificationsDropdown.contains(e.target) && !notificationBell.contains(e.target)) {
                notificationsDropdown.classList.add('hidden');
            }
        });
    }
    
    // Mark all as read button
    const markAllReadBtn = document.getElementById('markAllReadBtn');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', async (e) => {
            e.stopPropagation();
            await markAllAsRead();
        });
    }
    
    // Clear all notifications button
    const clearAllBtn = document.getElementById('clearAllNotificationsBtn');
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', async (e) => {
            e.stopPropagation();
            await clearAllNotifications();
        });
    }
    
    // Start notification system
    initNotificationSystem();
    
    // ============================================
    // 📧 EMAIL MANAGEMENT MODAL
    // ============================================
    
    const emailModal = document.getElementById('emailManagementModal');
    const emailBtn = document.getElementById('emailManagementBtn');
    const closeEmailModalBtn = document.getElementById('closeEmailModal');
    const cancelEmailModalBtn = document.getElementById('cancelEmailModal');
    
    if (emailBtn && emailModal) {
        emailBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            emailModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
    }
    
    function closeEmailModalFunc() {
        if (emailModal) {
            emailModal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }
    
    if (closeEmailModalBtn) {
        closeEmailModalBtn.addEventListener('click', closeEmailModalFunc);
    }
    if (cancelEmailModalBtn) {
        cancelEmailModalBtn.addEventListener('click', closeEmailModalFunc);
    }
    
    if (emailModal) {
        emailModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeEmailModalFunc();
            }
        });
    }
    
    // Close email modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && emailModal && !emailModal.classList.contains('hidden')) {
            closeEmailModalFunc();
        }
    });
    
    // Make close function globally accessible
    window.closeEmailModal = closeEmailModalFunc;
    
    // ============================================
    // 📧 EMAIL ACCOUNT FUNCTIONS
    // ============================================
    
    function syncAccount(accountId) {
        if (!confirm('Sync this email account to fetch new emails?')) return;
        
        const button = event?.target?.closest('button');
        if (!button) return;
        
        const originalHtml = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;
        
        fetch(`/email-accounts/${accountId}/sync`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Sync completed successfully!', 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                showToast(data.message || 'Sync failed. Please try again.', 'error');
                button.innerHTML = originalHtml;
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Sync error:', error);
            showToast('An error occurred during sync.', 'error');
            button.innerHTML = originalHtml;
            button.disabled = false;
        });
    }

    function syncAllEmails() {
        if (!confirm('Sync all your email accounts to fetch new emails?')) return;
        
        const accounts = document.querySelectorAll('.account-item');
        let synced = 0;
        let total = accounts.length;
        
        if (total === 0) {
            showToast('No email accounts to sync.', 'info');
            return;
        }
        
        showToast(`Syncing ${total} account(s)...`, 'info');
        
        accounts.forEach((account, index) => {
            const accountId = account.getAttribute('data-account-id') || 
                             account.querySelector('[onclick*="syncAccount"]')?.getAttribute('onclick')?.match(/\d+/)?.[0];
            
            if (accountId) {
                fetch(`/email-accounts/${accountId}/sync`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    synced++;
                    if (synced === total) {
                        showToast(`All ${total} account(s) synced successfully!`, 'success');
                        setTimeout(() => location.reload(), 2000);
                    }
                })
                .catch(error => {
                    console.error('Sync error for account:', accountId, error);
                    synced++;
                    if (synced === total) {
                        showToast(`Synced ${synced - 1}/${total} accounts. Some had errors.`, 'warning');
                        setTimeout(() => location.reload(), 3000);
                    }
                });
            } else {
                synced++;
            }
        });
    }

    function setPrimaryAccount(accountId) {
        if (!confirm('Set this as your primary email account?')) return;
        
        fetch(`/email-accounts/${accountId}/set-primary`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Primary account set successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message || 'Failed to set primary account.', 'error');
            }
        })
        .catch(error => {
            console.error('Set primary error:', error);
            showToast('An error occurred. Please try again.', 'error');
        });
    }

    function deleteAccount(accountId, email) {
        if (!confirm(`Are you sure you want to unlink the email account "${email}"?`)) return;
        if (!confirm(`This will permanently remove access to "${email}". Continue?`)) return;
        
        fetch(`/email-accounts/${accountId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Email account unlinked successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message || 'Failed to unlink email account.', 'error');
            }
        })
        .catch(error => {
            console.error('Delete error:', error);
            showToast('An error occurred. Please try again.', 'error');
        });
    }
    
    // Make functions globally accessible
    window.syncAccount = syncAccount;
    window.syncAllEmails = syncAllEmails;
    window.setPrimaryAccount = setPrimaryAccount;
    window.deleteAccount = deleteAccount;
    window.closeEmailModal = closeEmailModalFunc;
    
    // ============================================
    // SEARCH MODAL FUNCTIONALITY
    // ============================================
    
    const advancedSearchBtn = document.getElementById('advancedSearchBtn');
    const searchModal = document.getElementById('searchModal');
    const closeSearchModal = document.getElementById('closeSearchModal');
    const cancelSearch = document.getElementById('cancelSearch');
    const clearSearch = document.getElementById('clearSearch');
    const performSearch = document.getElementById('performSearch');
    const quickSearchInput = document.getElementById('quickSearchInput');
    const globalSearchInput = document.getElementById('globalSearchInput');
    const searchCategory = document.getElementById('searchCategory');
    
    if (advancedSearchBtn && searchModal) {
        advancedSearchBtn.addEventListener('click', function(e) {
            e.preventDefault();
            searchModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            if (globalSearchInput) globalSearchInput.focus();
            updateRecentSearches();
        });
    }
    
    function closeSearchModalFunc() {
        searchModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
    
    if (closeSearchModal) closeSearchModal.addEventListener('click', closeSearchModalFunc);
    if (cancelSearch) cancelSearch.addEventListener('click', closeSearchModalFunc);
    
    if (searchModal) {
        searchModal.addEventListener('click', function(e) {
            if (e.target === searchModal) closeSearchModalFunc();
        });
    }
    
    if (clearSearch) {
        clearSearch.addEventListener('click', function() {
            if (globalSearchInput) globalSearchInput.value = '';
            if (searchCategory) searchCategory.value = 'all';
            const exactMatch = document.getElementById('filterExactMatch');
            const caseSensitive = document.getElementById('filterCaseSensitive');
            if (exactMatch) exactMatch.checked = false;
            if (caseSensitive) caseSensitive.checked = false;
        });
    }
    
    if (performSearch) {
        performSearch.addEventListener('click', function() {
            const searchTerm = globalSearchInput ? globalSearchInput.value.trim() : '';
            const category = searchCategory ? searchCategory.value : 'all';
            const exactMatch = document.getElementById('filterExactMatch')?.checked || false;
            const caseSensitive = document.getElementById('filterCaseSensitive')?.checked || false;
            
            if (searchTerm) {
                addToRecentSearches(searchTerm, category);
                performGlobalSearch(searchTerm, category, exactMatch, caseSensitive);
                closeSearchModalFunc();
            }
        });
    }
    
    if (quickSearchInput) {
        quickSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const searchTerm = this.value.trim();
                if (searchTerm) {
                    addToRecentSearches(searchTerm, 'all');
                    performGlobalSearch(searchTerm, 'all', false, false);
                }
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === '/' && !e.ctrlKey && !e.metaKey && document.activeElement !== quickSearchInput) {
                e.preventDefault();
                quickSearchInput.focus();
            }
        });
    }
    
    const mobileSearchBtn = document.getElementById('mobileSearchBtn');
    if (mobileSearchBtn) {
        mobileSearchBtn.addEventListener('click', function() {
            searchModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            if (globalSearchInput) globalSearchInput.focus();
            updateRecentSearches();
        });
    }
    
    let recentSearches = JSON.parse(localStorage.getItem('tenantRecentSearches') || '[]');
    
    function addToRecentSearches(term, category) {
        const search = { term, category, timestamp: Date.now() };
        recentSearches = recentSearches.filter(s => !(s.term === term && s.category === category));
        recentSearches.unshift(search);
        recentSearches = recentSearches.slice(0, 10);
        localStorage.setItem('tenantRecentSearches', JSON.stringify(recentSearches));
    }
    
    function updateRecentSearches() {
        const container = document.getElementById('recentSearchesList');
        const parent = document.getElementById('recentSearches');
        
        if (recentSearches.length > 0 && parent && container) {
            parent.classList.remove('hidden');
            container.innerHTML = '';
            recentSearches.forEach(search => {
                const tag = document.createElement('div');
                tag.className = 'recent-search-tag';
                tag.textContent = `${search.term} (${search.category})`;
                tag.addEventListener('click', function() {
                    if (globalSearchInput) globalSearchInput.value = search.term;
                    if (searchCategory) searchCategory.value = search.category;
                });
                container.appendChild(tag);
            });
        } else if (parent) {
            parent.classList.add('hidden');
        }
    }
    
    function performGlobalSearch(term, category, exactMatch, caseSensitive) {
        const searchParams = new URLSearchParams();
        searchParams.append('search', term);
        if (category !== 'all') searchParams.append('category', category);
        if (exactMatch) searchParams.append('exact_match', '1');
        if (caseSensitive) searchParams.append('case_sensitive', '1');
        
        const currentPath = window.location.pathname;
        let searchUrl = '#';
        
        if (currentPath.includes('/invoices')) {
            searchUrl = '{{ route("tenant.invoices.index") }}?' + searchParams.toString();
        } else {
            searchUrl = '{{ route("tenant.dashboard") }}?' + searchParams.toString();
        }
        
        window.location.href = searchUrl;
    }
    
    // ============================================
    // THEME SETTINGS MODAL FUNCTIONALITY
    // ============================================
    
    const themeSettingsButton = document.getElementById('themeSettingsButton');
    const themeSettingsModal = document.getElementById('themeSettingsModal');
    const closeThemeModal = document.getElementById('closeThemeModal');
    const closeThemeModalBtn = document.getElementById('closeThemeModalBtn');
    
    let selectedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
    let selectedAppearance = localStorage.getItem('appearance') || 'system';
    
    function getCurrentAppearance() {
        const currentTheme = document.body.getAttribute('data-theme');
        if (currentTheme === 'light' || currentTheme === 'dark') return currentTheme;
        const savedAppearance = localStorage.getItem('appearance');
        if (savedAppearance === 'light' || savedAppearance === 'dark') return savedAppearance;
        return 'system';
    }
    
    function getSystemAppearance() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    
    function applyAppearance(appearance) {
        if (appearance === 'system') {
            const systemTheme = getSystemAppearance();
            document.body.setAttribute('data-theme', systemTheme);
            selectedAppearance = 'system';
        } else {
            document.body.setAttribute('data-theme', appearance);
            selectedAppearance = appearance;
        }
        localStorage.setItem('appearance', selectedAppearance);
    }
    
    selectedAppearance = getCurrentAppearance();
    
    if (themeSettingsButton && themeSettingsModal) {
        themeSettingsButton.addEventListener('click', function() {
            themeSettingsModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            selectedAppearance = getCurrentAppearance();
            selectedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
            setThemeSelection(selectedSidebarTheme);
            setAppearanceSelection(selectedAppearance);
        });
    }
    
    const closeThemeModalFunc = function() {
        themeSettingsModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    };
    
    if (closeThemeModal) closeThemeModal.addEventListener('click', closeThemeModalFunc);
    if (closeThemeModalBtn) closeThemeModalBtn.addEventListener('click', closeThemeModalFunc);
    
    if (themeSettingsModal) {
        themeSettingsModal.addEventListener('click', function(e) {
            if (e.target === themeSettingsModal) closeThemeModalFunc();
        });
    }
    
    const themeOptions = document.querySelectorAll('#themeSettingsModal .theme-option-compact');
    themeOptions.forEach(option => {
        option.addEventListener('click', function() {
            const theme = this.getAttribute('data-theme');
            setThemeSelection(theme);
            selectedSidebarTheme = theme;
            document.body.setAttribute('data-sidebar-theme', theme);
            localStorage.setItem('sidebarTheme', theme);
        });
    });
    
    const appearanceOptions = document.querySelectorAll('#themeSettingsModal .appearance-option-compact');
    appearanceOptions.forEach(option => {
        option.addEventListener('click', function() {
            const appearance = this.getAttribute('data-theme');
            setAppearanceSelection(appearance);
            applyAppearance(appearance);
        });
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (searchModal && !searchModal.classList.contains('hidden')) closeSearchModalFunc();
            if (themeSettingsModal && !themeSettingsModal.classList.contains('hidden')) closeThemeModalFunc();
            if (notificationsDropdown && !notificationsDropdown.classList.contains('hidden')) notificationsDropdown.classList.add('hidden');
            if (emailModal && !emailModal.classList.contains('hidden')) closeEmailModalFunc();
        }
    });
    
    function setThemeSelection(theme) {
        themeOptions.forEach(opt => {
            const existingIndicator = opt.querySelector('.current-selection');
            if (existingIndicator) existingIndicator.remove();
            
            if (opt.getAttribute('data-theme') === theme) {
                opt.classList.add('active');
                const indicator = document.createElement('div');
                indicator.className = 'current-selection';
                indicator.innerHTML = '<i class="fas fa-check"></i>';
                opt.appendChild(indicator);
            } else {
                opt.classList.remove('active');
            }
        });
    }
    
    function setAppearanceSelection(appearance) {
        appearanceOptions.forEach(opt => {
            const existingIndicator = opt.querySelector('.current-selection');
            if (existingIndicator) existingIndicator.remove();
            
            if (opt.getAttribute('data-theme') === appearance) {
                opt.classList.add('active');
                const indicator = document.createElement('div');
                indicator.className = 'current-selection';
                indicator.innerHTML = '<i class="fas fa-check"></i>';
                opt.appendChild(indicator);
            } else {
                opt.classList.remove('active');
            }
        });
    }
    
    const savedSidebarTheme = localStorage.getItem('sidebarTheme') || 'default';
    const savedAppearance = localStorage.getItem('appearance') || 'system';
    
    document.body.setAttribute('data-sidebar-theme', savedSidebarTheme);
    applyAppearance(savedAppearance);
    
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
        if (selectedAppearance === 'system') applyAppearance('system');
    });
});
</script>