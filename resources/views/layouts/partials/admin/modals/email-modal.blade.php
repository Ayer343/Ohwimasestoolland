{{-- ============ EMAIL ACCOUNT MANAGEMENT MODAL ============ --}}
@if($isAuthorized ?? false)
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
                    @if($isSuperAdmin ?? false)
                        Manage your email accounts
                    @elseif($isAdmin ?? false)
                        View and use email accounts linked by Super Admin
                    @else
                        Manage your email accounts
                    @endif
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
                // ✅ FIX: Get the Super Admin user
                $superAdmin = \App\Models\User::where('type', 0)->first();
                
                if ($isSuperAdmin ?? false) {
                    // Super Admin: Show their own accounts
                    $emailAccounts = auth()->user()->emailAccounts()->get();
                    $userNames = [];
                } elseif ($isAdmin ?? false) {
                    // Admin: Show ONLY the Super Admin's email accounts
                    if ($superAdmin) {
                        $emailAccounts = $superAdmin->emailAccounts()->get();
                        // Get Super Admin's name for display
                        $userNames = [];
                        foreach ($emailAccounts as $account) {
                            try {
                                $userNames[$account->user_id] = $superAdmin->name ?? 'Super Admin';
                            } catch (\Exception $e) {
                                $userNames[$account->user_id] = 'Super Admin';
                            }
                        }
                    } else {
                        $emailAccounts = collect();
                        $userNames = [];
                    }
                } else {
                    // For other users, show their own accounts
                    $emailAccounts = $user ? $user->emailAccounts()->get() : collect();
                    $userNames = [];
                }
                
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
            
            <!-- ⚠️ Admin Info: Cannot link own email -->
            @if($isAdmin ?? false)
                <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(59, 130, 246, 0.1); border: 1px solid #3b82f6;">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-blue-500 mr-3 mt-0.5"></i>
                        <div>
                            <p class="text-sm font-medium" style="color: var(--text-primary);">Email Account Management</p>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                As an Admin, you cannot link your own email account. Email accounts are managed by 
                                <strong>Super Admin</strong> and shared with all administrators. You can view and use 
                                the email accounts linked by Super Admin.
                            </p>
                            @if($totalAccounts === 0)
                                <p class="text-sm mt-2" style="color: var(--warning);">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    No email accounts have been linked by Super Admin yet. Please contact your Super Admin to set up email accounts.
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
            
            <!-- Quick Actions -->
            <div class="mb-6">
                <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i>Quick Actions
                </h4>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    {{-- ⚠️ Admin cannot link accounts - only Super Admin can --}}
                    @if($isSuperAdmin ?? false)
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
                    @endif
                    
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
                        @if($isSuperAdmin ?? false)
                            Your Email Accounts
                        @elseif($isAdmin ?? false)
                            Super Admin's Email Accounts (Shared)
                        @else
                            Your Email Accounts
                        @endif
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
                                $ownerName = $userNames[$account->user_id] ?? ($superAdmin->name ?? 'Super Admin');
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
                                            @if($isAdmin ?? false)
                                                <span class="ml-2 px-1.5 py-0.5 text-[10px] rounded-full" style="background-color: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                                                    <i class="fas fa-crown mr-0.5"></i> Shared by Super Admin
                                                </span>
                                            @endif
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
                                    @if(($isSuperAdmin ?? false) && !$account->is_primary && $account->status === 'verified')
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
                                    @if($isSuperAdmin ?? false)
                                        <a href="{{ route('email-accounts.edit', $account) }}" 
                                           class="p-1.5 rounded transition-colors duration-200 hover:bg-opacity-20"
                                           style="color: var(--text-secondary);"
                                           onclick="closeEmailModal()"
                                           title="Edit account">
                                            <i class="fas fa-edit text-xs"></i>
                                        </a>
                                    @endif
                                    <button onclick="syncAccount('{{ $account->id }}')" 
                                            class="p-1.5 rounded transition-colors duration-200 hover:bg-opacity-20"
                                            style="color: var(--text-secondary);"
                                            title="Sync emails">
                                        <i class="fas fa-sync text-xs"></i>
                                    </button>
                                    @if($isSuperAdmin ?? false)
                                        <button onclick="deleteAccount('{{ $account->id }}', '{{ $account->email }}')" 
                                                class="p-1.5 rounded transition-colors duration-200 hover:bg-opacity-20"
                                                style="color: var(--text-secondary);"
                                                title="Delete account">
                                            <i class="fas fa-trash text-xs"></i>
                                        </button>
                                    @endif
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
                        @if($isAdmin ?? false)
                            No email accounts have been linked by Super Admin yet.
                            <br>Please contact your Super Admin to set up email accounts.
                        @elseif($isSuperAdmin ?? false)
                            You haven't linked any email accounts yet.
                        @else
                            You haven't linked any email accounts yet.
                        @endif
                    </p>
                    @if($isSuperAdmin ?? false)
                        <a href="{{ route('email-accounts.create') }}" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200"
                           style="background-color: var(--primary); color: white;"
                           onclick="closeEmailModal()">
                            <i class="fas fa-plus mr-2"></i>Link Your First Account
                        </a>
                    @endif
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