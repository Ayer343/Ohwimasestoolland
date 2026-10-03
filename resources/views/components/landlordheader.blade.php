{{-- resources/views/components/landlord/sidebar.blade.php --}}

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
            $systemShortName = $systemSettings->system_short_name ?? config('app.short_name', 'Landlord');
            $systemName = $systemSettings->system_name ?? config('app.name', 'Laravel');
            
            // Get unread notifications count for sidebar
            $sidebarUnreadCount = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0;
            
            // Count pending ownership transfers for the current landlord
            $pendingTransferCount = \App\Models\PropertyOwnershipTransfer::where('current_landlord_id', auth()->id())
                ->where('status', 'pending')
                ->count();
            
            // Get email accounts for landlord
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
            
            // ✅ Get pending waste collection approvals for the landlord
            $pendingWasteApprovals = 0;
            if (auth()->check()) {
                $pendingWasteApprovals = \App\Models\WasteCollectionRequest::whereHas('property', function($q) {
                    $q->where('landlord_id', auth()->id());
                })->where('approval_status', 'pending')->count();
            }
        @endphp
        
        <div class="logo-container">
            <div class="logo-wrapper">
                @if($systemLogo)
                    <div class="logo-image-container">
                        <img src="{{ Storage::url($systemLogo) }}" 
                             alt="{{ $systemName }}" 
                             class="logo-image"
                             onerror="this.style.display='none'; document.getElementById('logoFallback').style.display='flex';">
                        <div id="logoFallback" class="logo-fallback" style="display: none;">
                            <i class="fas fa-building"></i>
                        </div>
                    </div>
                @else
                    <div class="logo-default">
                        <i class="fas fa-building"></i>
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
    
    <div class="logo-separator"></div>
    
    <!-- Compact navigation with minimal gap -->
    <nav class="mt-2">
        <div class="nav-divider">
            <span class="menu-text">MAIN NAVIGATION</span>
        </div>
        
        <!-- Dashboard -->
        <a href="{{ route('landlord.dashboard') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('landlord.dashboard') ? 'active' : '' }}">
            <i class="fas fa-home mr-4"></i>
            <span class="nav-text">Dashboard</span>
        </a>
        
        <!-- My Property -->
        <a href="{{ route('properties.my-properties') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('properties.my-properties') ? 'active' : '' }}">
            <i class="fas fa-building mr-4"></i>
            <span class="nav-text">My Property</span>
        </a>
        
        <!-- Property Units -->
        <a href="{{ route('property-units.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('property-units.*') && !request()->routeIs('property-units.pending-approvals') ? 'active' : '' }}">
            <i class="fas fa-door-open mr-4"></i>
            <span class="nav-text">Property Units</span>
        </a>
        
        <!-- My Tenants -->
        <a href="{{ route('landlord.tenants.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('landlord.tenants.*') ? 'active' : '' }}">
            <i class="fas fa-users mr-4"></i>
            <span class="nav-text">My Tenants</span>
        </a>

        <!-- Pending Approvals (Unit Approvals) -->
        <a href="{{ route('property-units.pending-approvals') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('property-units.pending-approvals') ? 'active' : '' }}">
            <i class="fas fa-user-clock mr-4"></i>
            <span class="nav-text">Unit Approvals</span>
        </a>
        
        <!-- ============================================ -->
        <!-- 💬 COMMUNICATION SECTION -->
        <!-- ============================================ -->
        <div class="nav-divider mt-4">
            <span class="menu-text">COMMUNICATION</span>
        </div>

        <!-- Email Management Button -->
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
        
        <!-- ============================================ -->
<!-- ♻️ WASTE COLLECTION (Landlord View)          -->
<!-- ============================================ -->
<div class="nav-divider mt-4">
    <span class="menu-text">WASTE COLLECTION</span>
</div>

<!-- Collection Approvals -->
<a href="{{ route('landlord.waste.approvals') }}" 
   class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('landlord.waste.approvals') ? 'active' : '' }}">
    <i class="fas fa-check-double mr-4"></i>
    <span class="nav-text">Collection Approvals</span>
    @if($pendingWasteApprovals > 0)
        <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center animate-pulse">
            {{ $pendingWasteApprovals > 9 ? '9+' : $pendingWasteApprovals }}
        </span>
    @endif
</a>

<!-- My Collection Requests -->
<a href="{{ route('landlord.waste.requests') }}" 
   class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('landlord.waste.requests') ? 'active' : '' }}">
    <i class="fas fa-trash-alt mr-4"></i>
    <span class="nav-text">My Collection Requests</span>
</a>

<!-- Collection History -->
<a href="{{ route('landlord.waste.history') }}" 
   class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('landlord.waste.history') ? 'active' : '' }}">
    <i class="fas fa-history mr-4"></i>
    <span class="nav-text">Collection History</span>
</a>
        
        <!-- ==================== CONSTRUCTION CONTRACTS ==================== -->
        <div class="nav-divider mt-4">
            <span class="menu-text">CONSTRUCTION</span>
        </div>
        
        <a href="{{ route('landlord.construction.contract.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('landlord.construction.contract.*') ? 'active' : '' }}">
            <i class="fas fa-file-contract mr-4"></i>
            <span class="nav-text">Construction Contracts</span>
            @php
                $pendingContractsCount = 0;
                if (class_exists('App\Models\ConstructionContract')) {
                    $pendingContractsCount = \App\Models\ConstructionContract::where('landlord_id', auth()->id())
                        ->where('status', 'pending_approval')
                        ->count();
                }
            @endphp
            @if($pendingContractsCount > 0)
                <span class="ml-auto bg-yellow-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                    {{ $pendingContractsCount }}
                </span>
            @endif
        </a>
        
        <!-- ==================== OWNERSHIP TRANSFER SECTION ==================== -->
        <div class="nav-divider mt-4">
            <span class="menu-text">OWNERSHIP TRANSFER</span>
        </div>

        <!-- Ownership Transfer Request -->
        <div class="dropdown-group">
            <a href="#" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('landlord.ownership-transfers.*') || request()->routeIs('landlord.ownership-history') ? 'active' : '' }}">
                <i class="fas fa-exchange-alt mr-4"></i>
                <span class="nav-text">Transfer Ownership</span>
                @if($pendingTransferCount > 0)
                    <span class="ml-auto bg-yellow-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                        {{ $pendingTransferCount }}
                    </span>
                @endif
                <i class="fas fa-chevron-down ml-auto transition-transform duration-200 dropdown-arrow"></i>
            </a>
            <div class="dropdown-content hidden">
                <a href="#" class="dropdown-item" onclick="return initiateTransferFromNav()">
                    <i class="fas fa-handshake mr-3"></i>
                    <span>Initiate New Transfer</span>
                </a>
                
                <a href="{{ route('landlord.ownership-transfers.index') }}" class="dropdown-item">
                    <i class="fas fa-list mr-3"></i>
                    <span>All Transfers</span>
                    @if($pendingTransferCount > 0)
                        <span class="ml-auto bg-yellow-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                            {{ $pendingTransferCount }}
                        </span>
                    @endif
                </a>
                
                <a href="{{ route('landlord.ownership-history') }}" class="dropdown-item">
                    <i class="fas fa-history mr-3"></i>
                    <span>Transfer History</span>
                </a>
                
                <a href="{{ route('landlord.ownership-transfers.completed') }}" class="dropdown-item">
                    <i class="fas fa-check-circle mr-3"></i>
                    <span>Completed Transfers</span>
                </a>
            </div>
        </div>

        <!-- Quick Property Transfer -->
        <a href="#" class="nav-item flex items-center py-2 px-6" onclick="return quickPropertyTransfer()">
            <i class="fas fa-arrow-right mr-4"></i>
            <span class="nav-text">Quick Transfer</span>
            <span class="ml-auto bg-green-500 text-white text-xs rounded-full px-2 py-1">
                Quick
            </span>
        </a>
        
        <!-- ==================== FINANCIAL SECTION ==================== -->
        <div class="nav-divider mt-4">
            <span class="menu-text">FINANCIAL</span>
        </div>
        
        <!-- Payment History -->
        <a href="{{ route('landlord.payments.history') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('landlord.payments.history') ? 'active' : '' }}">
            <i class="fas fa-history mr-4"></i>
            <span class="nav-text">Payment History</span>
        </a>

        <!-- My Invoices -->
        <a href="{{ route('landlord.invoices') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('landlord.invoices') ? 'active' : '' }}">
            <i class="fas fa-file-invoice mr-4"></i>
            <span class="nav-text">My Invoices</span>
        </a>
        
        <!-- ==================== SETTINGS SECTION ==================== -->
        <div class="nav-divider mt-4">
            <span class="menu-text">SETTINGS</span>
        </div>
        
        <!-- Profile Settings -->
        <a href="{{ route('landlord.profile.edit') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('landlord.profile.edit') ? 'active' : '' }}">
            <i class="fas fa-user-cog mr-4"></i>
            <span class="nav-text">Profile Settings</span>
        </a>
    </nav>
    
    <!-- Settings Button -->
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
        
        <div class="modal-body p-6 overflow-y-auto" style="max-height: calc(100vh - 12rem); scroll-behavior: smooth;">
            
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
        <div class="theme-modal-header-compact flex justify-between items-center">
            <h3 class="text-base font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-palette mr-2"></i>Theme Settings
            </h3>
            <button id="closeThemeModal" class="p-1 rounded-full hover:bg-opacity-20 transition-colors duration-200"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="theme-modal-body-compact">
            <div class="mb-6">
                <h4 class="section-header-compact" style="color: var(--text-primary);">Appearance</h4>
                <p class="section-description-compact" style="color: var(--text-secondary);">Choose how the application looks</p>
                
                <div class="appearance-grid-compact">
                    <div class="appearance-option-compact relative" data-theme="light">
                        <div class="appearance-icon-compact" style="background-color: var(--light-theme-bg); border: 1px solid var(--border-color);">
                            <i class="fas fa-sun" style="color: var(--warning);"></i>
                        </div>
                        <span class="appearance-label-compact" style="color: var(--text-primary);">Light</span>
                    </div>
                    
                    <div class="appearance-option-compact relative" data-theme="dark">
                        <div class="appearance-icon-compact" style="background-color: var(--dark-theme-bg); border: 1px solid var(--border-color);">
                            <i class="fas fa-moon" style="color: var(--info);"></i>
                        </div>
                        <span class="appearance-label-compact" style="color: var(--text-primary);">Dark</span>
                    </div>
                    
                    <div class="appearance-option-compact relative" data-theme="system">
                        <div class="appearance-icon-compact" style="background: linear-gradient(to right, var(--light-theme-bg) 0%, var(--dark-theme-bg) 100%); border: 1px solid var(--border-color);">
                            <i class="fas fa-desktop" style="color: var(--text-secondary);"></i>
                        </div>
                        <span class="appearance-label-compact" style="color: var(--text-primary);">System</span>
                    </div>
                </div>
            </div>
            
            <div class="mb-4">
                <h4 class="section-header-compact" style="color: var(--text-primary);">Sidebar Theme</h4>
                <p class="section-description-compact" style="color: var(--text-secondary);">Customize your sidebar appearance</p>
                
                <div class="theme-grid-compact">
                    <div class="theme-option-compact relative" data-theme="default">
                        <div class="theme-preview-compact" style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);"></div>
                        <span class="theme-label-compact" style="color: var(--text-primary);">Default</span>
                    </div>
                    
                    <div class="theme-option-compact relative" data-theme="dark">
                        <div class="theme-preview-compact" style="background: linear-gradient(135deg, #1f2937 0%, #374151 100%);"></div>
                        <span class="theme-label-compact" style="color: var(--text-primary);">Dark</span>
                    </div>
                    
                    <div class="theme-option-compact relative" data-theme="light">
                        <div class="theme-preview-compact" style="background: linear-gradient(135deg, #ffffff 0%, #f3f4f6 100%); border: 1px solid var(--border-color);"></div>
                        <span class="theme-label-compact" style="color: var(--text-primary);">Light</span>
                    </div>
                    
                    <div class="theme-option-compact relative" data-theme="blue">
                        <div class="theme-preview-compact" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);"></div>
                        <span class="theme-label-compact" style="color: var(--text-primary);">Blue</span>
                    </div>
                    
                    <div class="theme-option-compact relative" data-theme="green">
                        <div class="theme-preview-compact" style="background: linear-gradient(135deg, #059669 0%, #047857 100%);"></div>
                        <span class="theme-label-compact" style="color: var(--text-primary);">Green</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="theme-modal-footer-compact flex justify-end" style="border-color: var(--border-color);">
            <button id="closeThemeModalBtn" class="px-3 py-1.5 text-sm font-medium transition-colors duration-200"
                    style="color: var(--text-secondary); hover:color: var(--text-primary);">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Ownership Transfer Modal -->
<div id="transferModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden overflow-y-auto" 
     style="padding: 2rem 1rem;">
    <div class="rounded-lg shadow-xl w-11/12 md:w-2/3 lg:w-1/2 max-w-2xl mx-auto transfer-modal-container"
         style="background-color: var(--card-bg); 
                border: 1px solid var(--border-color);
                animation: modalSlideUp 0.3s ease-out;
                margin-top: auto;
                margin-bottom: auto;">
        
        <div class="px-6 py-4 border-b flex justify-between items-center sticky top-0 z-10"
             style="border-color: var(--border-color);
                    background-color: var(--card-bg);
                    border-radius: 12px 12px 0 0;">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-exchange-alt mr-2" style="color: var(--primary);"></i>
                Transfer Property Ownership
            </h3>
            <button id="closeTransferModal" class="p-1 rounded-full transition-colors duration-200 hover:bg-opacity-20"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <div class="p-6 overflow-y-auto modal-body" 
             style="max-height: calc(100vh - 200px); min-height: 200px;">
            
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2" 
                       style="color: var(--text-primary);">
                    Select Property to Transfer
                </label>
                <select id="transferPropertySelect" 
                        class="w-full px-4 py-2 rounded-lg focus:ring-2 transition-colors duration-200 appearance-none"
                        style="background-color: var(--bg-secondary); 
                               color: var(--text-primary);
                               border: 1px solid var(--border-color);
                               outline: none;
                               background-image: url(\"data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e\");
                               background-position: right 0.5rem center;
                               background-repeat: no-repeat;
                               background-size: 1.5em 1.5em;
                               padding-right: 2.5rem;">
                    <option value="">-- Select a Property --</option>
                    @php
                        $properties = \App\Models\Property::where('landlord_id', auth()->id())
                            ->whereDoesntHave('currentOwnershipTransfer')
                            ->select('id', 'property_name', 'registration_pattern')
                            ->get();
                    @endphp
                    @foreach($properties as $property)
                        <option value="{{ $property->id }}">
                            {{ $property->property_name }} ({{ $property->registration_pattern }})
                        </option>
                    @endforeach
                </select>
            </div>
            
            <div id="transferFormContainer" class="hidden">
                <!-- Form will be loaded dynamically via AJAX -->
            </div>
            
            <div class="mt-4 p-3 rounded-lg" 
                 style="background-color: rgba(var(--primary-rgb), 0.1); 
                        border: 1px solid rgba(var(--primary-rgb), 0.3);">
                <p class="text-sm" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i>
                    Select a property above to begin the ownership transfer process.
                </p>
            </div>
        </div>
        
        <div class="px-6 py-4 border-t flex justify-between sticky bottom-0 z-10"
             style="border-color: var(--border-color);
                    background-color: var(--bg-secondary);
                    border-radius: 0 0 12px 12px;">
            <button id="cancelTransfer" 
                    class="px-4 py-2 text-sm font-medium transition-colors duration-200"
                    style="color: var(--text-secondary); hover:color: var(--text-primary);">
                Cancel
            </button>
            <button id="proceedToTransfer" 
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200"
                    style="background-color: var(--primary); 
                           color: white;
                           hover:opacity: 0.9;">
                <i class="fas fa-arrow-right mr-2"></i>
                Proceed to Transfer
            </button>
        </div>
    </div>
</div>

<!-- Search Modal -->
<div id="searchModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden pt-20">
    <div class="rounded-lg shadow-xl w-11/12 md:w-2/3 lg:w-1/2 max-w-2xl search-modal-container"
         style="background-color: var(--card-bg); 
                border: 1px solid var(--border-color);">
        
        <div class="px-6 py-4 border-b flex justify-between items-center"
             style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-search mr-2" style="color: var(--primary);"></i>Advanced Search
            </h3>
            <button id="closeSearchModal" 
                    class="p-1 rounded-full transition-colors duration-200 hover:bg-opacity-20"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <div class="p-6">
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2" 
                       style="color: var(--text-primary);">
                    Search Term
                </label>
                <input type="text" id="globalSearchInput" placeholder="Enter search term..." 
                       class="w-full px-4 py-3 rounded-lg focus:ring-2 transition-colors duration-200"
                       style="background-color: var(--bg-secondary); 
                              color: var(--text-primary);
                              border: 1px solid var(--border-color);
                              outline: none;">
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2" 
                       style="color: var(--text-primary);">
                    Search Category
                </label>
                <select id="searchCategory" 
                        class="w-full px-4 py-3 rounded-lg focus:ring-2 transition-colors duration-200 appearance-none"
                        style="background-color: var(--bg-secondary); 
                               color: var(--text-primary);
                               border: 1px solid var(--border-color);
                               outline: none;
                               background-image: url(\"data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e\");
                               background-position: right 0.5rem center;
                               background-repeat: no-repeat;
                               background-size: 1.5em 1.5em;
                               padding-right: 2.5rem;">
                    <option value="all">All Categories</option>
                    <option value="properties">Properties</option>
                    <option value="property-units">Property Units</option>
                    <option value="tenants">Tenants</option>
                    <option value="payments">Payments</option>
                    <option value="invoices">Invoices</option>
                    <option value="ownership-transfers">Ownership Transfers</option>
                </select>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2" 
                       style="color: var(--text-primary);">
                    Search Filters
                </label>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" id="filterExactMatch" 
                                   class="rounded transition-colors duration-200"
                                   style="background-color: var(--bg-secondary);
                                          border-color: var(--border-color);
                                          color: var(--primary);">
                            <span class="ml-2 text-sm" 
                                  style="color: var(--text-secondary);">Exact Match</span>
                        </label>
                    </div>
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" id="filterCaseSensitive" 
                                   class="rounded transition-colors duration-200"
                                   style="background-color: var(--bg-secondary);
                                          border-color: var(--border-color);
                                          color: var(--primary);">
                            <span class="ml-2 text-sm" 
                                  style="color: var(--text-secondary);">Case Sensitive</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div id="recentSearches" class="hidden">
                <label class="block text-sm font-medium mb-2" 
                       style="color: var(--text-primary);">
                    Recent Searches
                </label>
                <div id="recentSearchesList" class="flex flex-wrap gap-2">
                </div>
            </div>
        </div>
        
        <div class="px-6 py-4 border-t flex justify-between"
             style="border-color: var(--border-color);
                    background-color: var(--bg-secondary);">
            <button id="clearSearch" 
                    class="px-4 py-2 text-sm font-medium transition-colors duration-200"
                    style="color: var(--text-secondary);
                           hover:color: var(--text-primary);">
                Clear
            </button>
            <div class="flex space-x-3">
                <button id="cancelSearch" 
                        class="px-4 py-2 text-sm font-medium transition-colors duration-200"
                        style="color: var(--text-secondary);
                               hover:color: var(--text-primary);">
                    Cancel
                </button>
                <button id="performSearch" 
                        class="px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200"
                        style="background-color: var(--primary); 
                               color: white;">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Header -->
<div class="content" id="mainContent">
    <!-- Top Navigation -->
    <header class="header flex items-center justify-between">
        <div class="flex items-center">
            <button id="toggleSidebarMobile" class="mobile-menu-btn mr-4" 
                    style="color: var(--text-secondary);">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">@yield('title', 'Dashboard')</h2>
        </div>
        
        <div class="flex items-center space-x-4">
            <div class="relative">
                <div class="flex items-center space-x-2">
                    <div class="relative hidden md:block">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2" 
                           style="color: var(--text-secondary);"></i>
                        <input type="text" id="quickSearchInput" placeholder="Quick search..." 
                               class="header-search pl-10 pr-10 transition-colors duration-200" 
                               style="color: var(--text-primary); 
                                      background-color: var(--bg-secondary); 
                                      border: 1px solid var(--border-color);
                                      outline: none;"
                               data-toggle="tooltip" title="Press / to focus">
                        <button id="advancedSearchBtn" 
                                class="absolute right-2 top-1/2 transform -translate-y-1/2 p-1 rounded transition-colors duration-200"
                                style="color: var(--text-secondary);
                                       hover:color: var(--text-primary);">
                            <i class="fas fa-sliders-h text-sm"></i>
                        </button>
                    </div>
                    
                    <button id="mobileSearchBtn" 
                            class="md:hidden p-2 rounded-full transition-colors duration-200"
                            style="color: var(--text-secondary);
                                   hover:background-color: var(--bg-secondary);">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            
            <div class="header-buttons flex items-center space-x-4">
                <!-- Notification Bell -->
                <div class="relative" x-data="{ open: false }" @click.away="open = false">
                    <button 
                        @click="open = !open; if(open) window.dispatchEvent(new CustomEvent('notifications-reload'))" 
                        class="relative p-2 rounded-full transition-colors duration-200"
                        id="notificationBell"
                        style="color: var(--text-secondary); hover:color: var(--text-primary);"
                        aria-label="Notifications"
                    >
                        <div class="relative">
                            <i class="fas fa-bell text-xl"></i>
                            @if($sidebarUnreadCount > 0)
                                <div class="glowing-red-light"></div>
                                <span class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 text-xs flex items-center justify-center glowing-badge">
                                    {{ $sidebarUnreadCount > 9 ? '9+' : $sidebarUnreadCount }}
                                </span>
                            @endif
                        </div>
                    </button>
                    
                    <div 
                        x-show="open" 
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 translate-y-0"
                        x-transition:leave-end="opacity-0 translate-y-1"
                        class="absolute right-0 mt-2 w-80 rounded-lg shadow-lg border z-50 notification-dropdown"
                        style="display: none; background-color: var(--card-bg); border-color: var(--border-color); box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);"
                    >
                        <div class="p-4 border-b" style="border-color: var(--border-color);">
                            <div class="flex justify-between items-center">
                                <h3 class="font-semibold" style="color: var(--text-primary);">
                                    <i class="fas fa-bell mr-2"></i>Notifications
                                </h3>
                                @if($sidebarUnreadCount > 0)
                                    <button 
                                        onclick="markAllAsRead()" 
                                        class="text-sm transition-colors duration-200"
                                        style="color: var(--primary); hover:color: var(--secondary);"
                                    >
                                        Mark all as read
                                    </button>
                                @endif
                            </div>
                        </div>
                        
                        <div class="max-h-96 overflow-y-auto" id="notificationList">
                            <div class="p-4 text-center" style="color: var(--text-secondary);">
                                <i class="fas fa-spinner fa-spin mr-2"></i>
                                Loading notifications...
                            </div>
                        </div>
                        
                        <div class="p-3 border-t" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                            <div class="flex justify-between items-center">
                                <a 
                                    href="{{ route('notifications.index') }}" 
                                    class="text-sm transition-colors duration-200"
                                    style="color: var(--primary); hover:color: var(--secondary);"
                                >
                                    <i class="fas fa-list mr-1"></i>
                                    View all
                                </a>
                                @if($sidebarUnreadCount > 0)
                                    <button 
                                        onclick="clearAllNotifications()" 
                                        class="text-sm transition-colors duration-200"
                                        style="color: var(--danger); hover:color: var(--danger-dark);"
                                    >
                                        <i class="fas fa-trash-alt mr-1"></i>
                                        Clear all
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Messages/Email -->
                <button class="relative p-2 rounded-full transition-colors duration-200"
                        style="color: var(--text-secondary);
                               hover:background-color: var(--bg-secondary);">
                    <div class="relative">
                        <i class="far fa-envelope text-xl"></i>
                        <div class="notification-dot glowing-red-dot"></div>
                    </div>
                </button>
            </div>
            
            <div class="dropdown relative">
                <button id="userMenuButton" class="flex items-center space-x-2">
                    <div class="avatar-minimal">
                        @if(Auth::user()->photo)
                            <img src="{{ Storage::url('public/users/photos/' . Auth::user()->photo) }}" 
                                 alt="{{ Auth::user()->name }}" 
                                 class="w-8 h-8 rounded-full object-cover"
                                 style="border: 1px solid var(--border-color);">
                        @else
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-semibold"
                                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
                                        border: 1px solid var(--border-color);">
                                {{ substr(Auth::user()->name, 0, 2) }}
                            </div>
                        @endif
                    </div>
                    <i class="fas fa-chevron-down text-xs" style="color: var(--text-secondary);"></i>
                </button>
                
                <div id="userDropdown" class="dropdown-menu" 
                     style="background-color: var(--card-bg); 
                            border: 1px solid var(--border-color);
                            color: var(--text-primary);">
                    <a href="{{ route('landlord.profile.edit') }}" class="dropdown-item">
                        <i class="far fa-user mr-3" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-primary);">My Profile</span>
                    </a>
                    
                    <a href="{{ route('notifications.index') }}" class="dropdown-item">
                        <i class="far fa-bell mr-3" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-primary);">Notifications</span>
                        @if($sidebarUnreadCount > 0)
                            <span class="ml-auto text-xs px-2 py-1 rounded-full glowing-badge" 
                                  style="background-color: var(--danger); color: white;">
                                {{ $sidebarUnreadCount > 9 ? '9+' : $sidebarUnreadCount }}
                            </span>
                        @endif
                    </a>
                    
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-question-circle mr-3" style="color: var(--text-secondary);"></i>
                        <span style="color: var(--text-primary);">Help & Support</span>
                    </a>
                    
                    <div class="border-t my-1" style="border-color: var(--border-color);"></div>
                    
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item w-full text-left"
                                style="color: var(--danger);">
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

@include('layouts.partials.landlord.styles')
@include('layouts.partials.landlord.scripts')