{{-- ============ CRITICAL FIX: Ensure sidebarUnreadCount is always defined ============ --}}
@php
    if (!isset($sidebarUnreadCount)) {
        $sidebarUnreadCount = 0;
    }
    if ($sidebarUnreadCount === 0 && auth()->check()) {
        try {
            $sidebarUnreadCount = auth()->user()->unreadNotifications()->count();
        } catch (\Exception $e) {
            $sidebarUnreadCount = 0;
        }
    }
@endphp

<!-- Overlay for mobile -->
<div class="overlay" id="overlay"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <!-- Enhanced Professional Logo Section -->
    <div class="logo-section">
        @php
            try {
                $systemSettings = \App\Models\SystemSetting::getSettings();
            } catch (\Throwable $e) {
                $systemSettings = null;
            }

            $systemLogo      = $systemSettings->system_logo ?? null;
            $systemShortName = $systemSettings->system_short_name ?? config('app.short_name', 'Admin');
            $systemName      = $systemSettings->system_name ?? config('app.name', 'Laravel');

            $systemLogoUrl = null;
            if ($systemLogo) {
                try {
                    $systemLogoUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($systemLogo);
                } catch (\Throwable $e) {
                    try {
                        $systemLogoUrl = \Illuminate\Support\Facades\Storage::url($systemLogo);
                    } catch (\Throwable $e2) {
                        $systemLogoUrl = null;
                    }
                }
            }

            $user = auth()->user();
            $currentUserType = $user->type ?? null;

            $hasSuperAdminAccess = ($currentUserType === 0) || $user->hasRole('super-admin');
            $hasAdminAccess      = ($currentUserType === 1) || $user->hasRole('admin');
            $hasDeveloperAccess  = ($currentUserType === 5) || $user->hasRole('developer');

            $isAuthorized = $hasSuperAdminAccess || $hasAdminAccess || $hasDeveloperAccess;

            $isSuperAdmin = $hasSuperAdminAccess;
            $isAdmin      = $hasAdminAccess;
            $isDeveloper  = $hasDeveloperAccess;

            // ✅ Explicit flag for the search input's Alpine scope
            $searchRole = $isSuperAdmin ? 'super-admin' : ($isAdmin ? 'admin' : ($isDeveloper ? 'developer' : 'guest'));

            $sidebarCurrentRole = session('selected_role');
            if (!$sidebarCurrentRole) {
                $currentRoute = Route::currentRouteName();
                if (str_contains($currentRoute, 'super-admin')) {
                    $sidebarCurrentRole = 'super-admin';
                } elseif (str_contains($currentRoute, 'admin')) {
                    $sidebarCurrentRole = 'admin';
                } elseif (str_contains($currentRoute, 'developer')) {
                    $sidebarCurrentRole = 'developer';
                } elseif (str_contains($currentRoute, 'landlord')) {
                    $sidebarCurrentRole = 'landlord';
                } elseif (str_contains($currentRoute, 'field-agent')) {
                    $sidebarCurrentRole = 'field-agent';
                } elseif (str_contains($currentRoute, 'security')) {
                    $sidebarCurrentRole = 'security-personnel';
                } elseif (str_contains($currentRoute, 'tenant')) {
                    $sidebarCurrentRole = 'tenant';
                } else {
                    $sidebarCurrentRole = 'admin';
                }
            }

            // Defaults
            $smsProviderConfigured      = false;
            $whatsappProviderConfigured = false;
            $smsQuickStatus             = ['system_ready' => false, 'can_send_sms' => false];
            $whatsappQuickStatus        = ['system_ready' => false, 'can_send_whatsapp' => false];
            $smsSystemStatus            = ['system_ready' => false, 'health_status' => 'error', 'status_message' => 'Service unavailable'];
            $whatsappSystemStatus       = ['system_ready' => false, 'health_status' => 'error', 'status_message' => 'Service unavailable'];
            $smsProviders               = [];
            $whatsappProviders          = [];
            $smsUsageStats              = ['total_sent' => 0, 'successful' => 0, 'failed' => 0, 'success_rate' => 0, 'today' => 0, 'this_month' => 0];
            $pendingSmsCount            = 0;
            $pendingOwnershipTransfers  = 0;
            $pendingSupervisorAssignments = 0;
            $landlordEligibleForArchive = 0;
            $landlordUnpaidPreviousYears = 0;
            $tenantEligibleForArchive   = 0;
            $tenantUnpaidPreviousYears  = 0;
            $pendingAgreements          = 0;
            $awaitingSignature          = 0;
            $pendingPayments            = 0;
            $totalBillingRecords        = 0;
            $completedPayments          = 0;
            $isPrimary                  = false;
            $totalWhatsAppProviders     = 0;

            if ($isAuthorized) {
                $pendingOwnershipTransfers = \App\Models\PropertyOwnershipTransfer::where('status', 'pending')->count();

                $pendingSupervisorAssignments = \App\Models\SecuritySupervisorAssignment::active()
                    ->where('end_date', '<=', now()->addDays(7))
                    ->count();

                $landlordEligibleForArchive = \App\Models\Invoice::where('status', 'paid')
                    ->whereNull('year_end_archived_at')
                    ->whereYear('created_at', '<', now()->year)
                    ->count();

                $landlordUnpaidPreviousYears = \App\Models\Invoice::where('status', '!=', 'paid')
                    ->where('status', '!=', 'cancelled')
                    ->where(function($q) {
                        $q->whereYear('created_at', '<', now()->year)
                          ->orWhere('period', '<', now()->year . '-01');
                    })
                    ->whereNull('deleted_at')
                    ->count();

                $tenantEligibleForArchive = \App\Models\TenantInvoice::where('status', 'paid')
                    ->whereNull('year_end_archived_at')
                    ->whereYear('created_at', '<', now()->year)
                    ->count();

                $tenantUnpaidPreviousYears = \App\Models\TenantInvoice::where('status', '!=', 'paid')
                    ->where('status', '!=', 'cancelled')
                    ->where(function($q) {
                        $q->whereYear('created_at', '<', now()->year)
                          ->orWhere('period', '<', now()->year . '-01');
                    })
                    ->whereNull('deleted_at')
                    ->count();

                $pendingAgreements = $isSuperAdmin ?
                    \App\Models\AdminBillingRecord::where('super_admin_id', auth()->id())
                        ->where('status', 'pending')->count() : 0;

                $awaitingSignature = $isSuperAdmin ?
                    \App\Models\AdminBillingRecord::where('super_admin_id', auth()->id())
                        ->where('status', 'pending')
                        ->whereNotNull('agreement_pdf_path')
                        ->whereNull('signed_agreement_pdf_path')
                        ->count() : 0;

                $pendingPayments = $isSuperAdmin ?
                    \App\Models\AgreementPayment::whereHas('agreement', function($query) {
                        $query->where('super_admin_id', auth()->id());
                    })
                    ->where('status', 'pending_confirmation')
                    ->count() : 0;

                $totalBillingRecords = $isSuperAdmin ?
                    \App\Models\AdminBillingRecord::where('super_admin_id', auth()->id())->count() : 0;

                $completedPayments = $isSuperAdmin ?
                    \App\Models\AgreementPayment::whereHas('agreement', function($query) {
                        $query->where('super_admin_id', auth()->id());
                    })
                    ->where('status', 'completed')
                    ->count() : 0;

                $isPrimary = $isSuperAdmin ?
                    \App\Models\AdminBillingRecord::where('super_admin_id', auth()->id())
                        ->where('is_primary_for_billing', true)
                        ->exists() : false;

                try {
                    $smsService = app(\App\Services\SmsService::class);
                    $smsSystemStatus = $smsService->getSystemStatus();
                    $smsQuickStatus = $smsService->getQuickStatus();
                    $smsProviders = $smsService->getAllProvidersWithStatus();
                    $smsUsageStats = $smsService->getUsageStatistics();

                    $smsProviderConfigured = (bool) (
                        ($smsSystemStatus['configured_providers'] ?? 0) > 0
                        || ($smsSystemStatus['enabled_providers'] ?? 0) > 0
                        || ($smsSystemStatus['can_send_sms'] ?? false)
                        || ($smsSystemStatus['system_ready'] ?? false)
                    );
                } catch (\Exception $e) {
                    $smsSystemStatus = ['system_ready' => false, 'health_status' => 'error', 'status_message' => 'Service unavailable'];
                    $smsQuickStatus = ['system_ready' => false, 'can_send_sms' => false];
                    $smsProviders = [];
                    $smsUsageStats = ['total_sent' => 0, 'successful' => 0, 'failed' => 0];
                    $smsProviderConfigured = false;
                }

                try {
                    $whatsappService = app(\App\Services\WhatsAppService::class);
                    $whatsappSystemStatus = $whatsappService->getSystemStatus();
                    $whatsappQuickStatus  = $whatsappService->getQuickStatus();
                    $whatsappProviders    = $whatsappService->getAllProvidersWithStatus();
                    $totalWhatsAppProviders = is_array($whatsappProviders) ? count($whatsappProviders) : 0;

                    $whatsappProviderConfigured = (bool) (
                        ($whatsappSystemStatus['configured'] ?? false)
                        || ($whatsappSystemStatus['enabled'] ?? false)
                        || ($whatsappSystemStatus['configured_providers'] ?? 0) > 0
                        || ($whatsappSystemStatus['enabled_providers'] ?? 0) > 0
                        || ($whatsappSystemStatus['can_send'] ?? false)
                        || ($whatsappSystemStatus['system_ready'] ?? false)
                    );
                } catch (\Exception $e) {
                    $whatsappSystemStatus = ['system_ready' => false, 'health_status' => 'error', 'status_message' => 'Service unavailable'];
                    $whatsappQuickStatus  = ['system_ready' => false, 'can_send_whatsapp' => false];
                    $whatsappProviders    = [];
                    $totalWhatsAppProviders = 0;
                    $whatsappProviderConfigured = false;
                }

                $pendingSmsCount = 0;
                try {
                    $pendingSmsCount = \App\Models\SmsLog::where('status', 'failed')
                        ->where('created_at', '>=', now()->subHours(24))
                        ->count();
                } catch (\Exception $e) {
                    $pendingSmsCount = 0;
                }
            }
        @endphp

        @if($isAuthorized)
        <div class="logo-container">
            <div class="logo-wrapper">
                @if($systemLogoUrl)
                    <div class="logo-image-container">
                        <img src="{{ $systemLogoUrl }}"
                             alt="{{ $systemName }}"
                             class="logo-image"
                             onerror="this.style.display='none'; var f=document.getElementById('logoFallback'); if(f) f.style.display='flex';">
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

    <!-- Professional separator -->
    <div class="logo-separator"></div>

<!-- Navigation -->
<nav class="mt-2">
    <div class="nav-divider">
        <span class="menu-text">MAIN NAVIGATION</span>
    </div>

    {{-- ============================================ --}}
    {{-- 🏠 ROLE-BASED DASHBOARD LINK --}}
    {{-- ============================================ --}}
    @if($isSuperAdmin)
        <a href="{{ route('super-admin.dashboard') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('super-admin.dashboard') ? 'active' : '' }}">
            <i class="fas fa-home mr-4"></i>
            <span class="nav-text">Dashboard</span>
        </a>
    @elseif($isAdmin)
        <a href="{{ route('admin.dashboard') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <i class="fas fa-home mr-4"></i>
            <span class="nav-text">Dashboard</span>
        </a>
    @elseif($isDeveloper)
        <a href="{{ route('developer.dashboard') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('developer.dashboard') ? 'active' : '' }}">
            <i class="fas fa-home mr-4"></i>
            <span class="nav-text">Dashboard</span>
        </a>
    @elseif(isset($isLandlord) && $isLandlord)
        <a href="{{ route('landlord.dashboard') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('landlord.dashboard') ? 'active' : '' }}">
            <i class="fas fa-home mr-4"></i>
            <span class="nav-text">Dashboard</span>
        </a>
    @elseif(isset($isTenant) && $isTenant)
        <a href="{{ route('tenant.dashboard') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('tenant.dashboard') ? 'active' : '' }}">
            <i class="fas fa-home mr-4"></i>
            <span class="nav-text">Dashboard</span>
        </a>
    @endif

    {{-- ============================================ --}}
    {{-- ⚙️ SYSTEM SETTINGS — Super Admin only --}}
    {{-- ============================================ --}}
    @if($isSuperAdmin)
        <div class="nav-divider mt-1">
            <span class="menu-text">SYSTEM SETTINGS</span>
        </div>

        <a href="{{ route('admin.system-settings.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('admin.system-settings.*') ? 'active' : '' }}">
            <i class="fas fa-cog mr-4"></i>
            <span class="nav-text">System Settings</span>
        </a>
    @endif

    {{-- ============================================ --}}
    {{-- 💰 BILLING MANAGEMENT — Super Admin only --}}
    {{-- ============================================ --}}
    @if($isSuperAdmin)
        <div class="nav-divider mt-1">
            <span class="menu-text">BILLING MANAGEMENT</span>
        </div>

        <button id="billingManagementBtn" class="nav-item flex items-center py-2 px-6 w-full text-left hover:bg-opacity-20 transition-colors duration-200" style="color: var(--sidebar-text);">
            <i class="fas fa-file-invoice-dollar mr-4"></i>
            <span class="nav-text flex-grow">Billing Management</span>
            @if(($pendingAgreements ?? 0) > 0 || ($awaitingSignature ?? 0) > 0 || ($pendingPayments ?? 0) > 0)
                <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                    {{ ($pendingAgreements ?? 0) + ($awaitingSignature ?? 0) + ($pendingPayments ?? 0) }}
                </span>
            @else
                <i class="fas fa-chevron-right ml-auto text-xs opacity-70"></i>
            @endif
        </button>
    @endif

    {{-- ============================================ --}}
    {{-- 📄 INVOICE MANAGEMENT --}}
    {{-- ============================================ --}}
    <div class="nav-divider mt-1">
        <span class="menu-text">INVOICE MANAGEMENT</span>
    </div>

    <button id="invoiceManagementBtn" class="nav-item flex items-center py-2 px-6 w-full text-left hover:bg-opacity-20 transition-colors duration-200" style="color: var(--sidebar-text);">
        <i class="fas fa-file-invoice-dollar mr-4"></i>
        <span class="nav-text flex-grow">Invoice Management</span>
        @php
            $totalInvoiceNotifications = ($landlordEligibleForArchive > 0 ? 1 : 0) +
                                        ($landlordUnpaidPreviousYears > 0 ? 1 : 0) +
                                        ($tenantEligibleForArchive > 0 ? 1 : 0) +
                                        ($tenantUnpaidPreviousYears > 0 ? 1 : 0);
        @endphp
        @if($totalInvoiceNotifications > 0)
            <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                {{ $totalInvoiceNotifications > 9 ? '9+' : $totalInvoiceNotifications }}
            </span>
        @else
            <i class="fas fa-chevron-right ml-auto text-xs opacity-70"></i>
        @endif
    </button>

    {{-- ============================================ --}}
    {{-- 💳 PAYMENT MANAGEMENT --}}
    {{-- ============================================ --}}
    @if($isSuperAdmin || $isAdmin || $isDeveloper)
        <div class="nav-divider mt-1">
            <span class="menu-text">PAYMENT MANAGEMENT</span>
        </div>

        @if($isSuperAdmin || $isDeveloper)
            <a href="{{ route('admin.payment-providers.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('admin.payment-providers.*') ? 'active' : '' }}">
                <i class="fas fa-credit-card mr-4"></i>
                <span class="nav-text">Payment Providers</span>
            </a>
        @endif

        <a href="{{ route('admin.payments.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}">
            <i class="fas fa-money-bill-wave mr-4"></i>
            <span class="nav-text">Payments</span>
        </a>
    @endif

    {{-- ============================================ --}}
    {{-- ⭐ CONSTRUCTION MANAGEMENT --}}
    {{-- ============================================ --}}
    <div class="nav-divider mt-1">
        <span class="menu-text">CONSTRUCTION MANAGEMENT</span>
    </div>

    <a href="{{ route('admin.construction.contracts.index') }}"
       class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('admin.construction.contracts.*') ? 'active' : '' }}">
        <i class="fas fa-file-contract mr-4"></i>
        <span class="nav-text">Construction Contracts</span>
        @php
            $pendingContractsCount = \App\Models\ConstructionContract::where('status', 'pending_approval')->count();
        @endphp
        @if($pendingContractsCount > 0)
            <span class="ml-auto bg-yellow-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                {{ $pendingContractsCount > 9 ? '9+' : $pendingContractsCount }}
            </span>
        @endif
    </a>

    <a href="{{ route('admin.construction-registrations.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('admin.construction-registrations.*') ? 'active' : '' }}">
        <i class="fas fa-hard-hat mr-4"></i>
        <span class="nav-text">Construction Registrations</span>
        @php
            $pendingConstructionCount = \App\Models\LandlordConstructionRegistration::where('status', 'pending')->count();
        @endphp
        @if($pendingConstructionCount > 0)
            <span class="ml-auto bg-yellow-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                {{ $pendingConstructionCount > 9 ? '9+' : $pendingConstructionCount }}
            </span>
        @endif
    </a>

    {{-- ============================================ --}}
    {{-- 💬 COMMUNICATION --}}
    {{-- ============================================ --}}
    <div class="nav-divider mt-1">
        <span class="menu-text">COMMUNICATION</span>
    </div>

    <button id="emailManagementBtn" class="nav-item flex items-center py-2 px-6 w-full text-left hover:bg-opacity-20 transition-colors duration-200 {{ request()->routeIs('email-accounts.*') ? 'active' : '' }}" style="color: var(--sidebar-text);">
        <i class="fas fa-envelope mr-4"></i>
        <span class="nav-text flex-grow">Email Management</span>
        @php
            $superAdmin = \App\Models\User::where('type', 0)->first();

            if ($isSuperAdmin || $isAdmin) {
                if ($isSuperAdmin) {
                    $emailAccounts = auth()->user()->emailAccounts()->get();
                } else {
                    if ($superAdmin) {
                        $emailAccounts = $superAdmin->emailAccounts()->get();
                    } else {
                        $emailAccounts = collect();
                    }
                }

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
            } else {
                $emailAccounts = $user ? $user->emailAccounts()->get() : collect();
                $pendingEmailCount = $emailAccounts->where('status', 'pending')->count();
                $failedEmailCount = $emailAccounts->where('status', 'failed')->count();
                $totalEmailIssues = $pendingEmailCount + $failedEmailCount;

                $totalUnreadEmails = 0;
                foreach ($emailAccounts as $account) {
                    $totalUnreadEmails += $account->emails()->where('is_read', false)->where('folder', 'INBOX')->count();
                }
            }
        @endphp
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

    @if($smsProviderConfigured)
    <button id="smsManagementBtn" class="nav-item flex items-center py-2 px-6 w-full text-left hover:bg-opacity-20 transition-colors duration-200 {{ request()->routeIs('sms.*') ? 'active' : '' }}" style="color: var(--sidebar-text);">
        <i class="fas fa-sms mr-4"></i>
        <span class="nav-text flex-grow">SMS Management</span>
        @if($pendingSmsCount > 0)
            <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                {{ $pendingSmsCount > 9 ? '9+' : $pendingSmsCount }}
            </span>
        @elseif(!($smsQuickStatus['system_ready'] ?? false))
            <span class="ml-auto bg-yellow-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-[10px]"></i>
            </span>
        @else
            <i class="fas fa-chevron-right ml-auto text-xs opacity-70"></i>
        @endif
    </button>
    @endif

    @if($whatsappProviderConfigured)
    <button id="whatsappManagementBtn" class="nav-item flex items-center py-2 px-6 w-full text-left hover:bg-opacity-20 transition-colors duration-200 {{ request()->routeIs('admin.whatsapp.*') || request()->routeIs('developer.whatsapp.*') ? 'active' : '' }}" style="color: var(--sidebar-text);">
        <i class="fab fa-whatsapp mr-4" style="color: #25D366;"></i>
        <span class="nav-text flex-grow">WhatsApp Management</span>
        @if(!($whatsappQuickStatus['system_ready'] ?? false))
            <span class="ml-auto bg-yellow-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-[10px]"></i>
            </span>
        @else
            <i class="fas fa-chevron-right ml-auto text-xs opacity-70"></i>
        @endif
    </button>
    @endif

    {{-- ============================================ --}}
{{-- 🛠️ SYSTEM MANAGEMENT --}}
{{-- ============================================ --}}
<div class="nav-divider mt-1">
    <span class="menu-text">SYSTEM MANAGEMENT</span>
</div>

<a href="{{ route('admin.users.index')}}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
    <i class="fas fa-users mr-4"></i>
    <span class="nav-text">Users</span>
</a>

<a href="{{ route('registration-plans.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('registration-plans.*') ? 'active' : '' }}">
    <i class="fas fa-map-marked-alt mr-4"></i>
    <span class="nav-text">Registration Plans</span>
</a>

<a href="{{ route('properties.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('properties.*') ? 'active' : '' }}">
    <i class="fas fa-building mr-4"></i>
    <span class="nav-text">Properties</span>
</a>

{{-- ✅ NEW: Family Links --}}
<a href="{{ route('admin.family-links.index') }}"
   class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('admin.family-links.*') ? 'active' : '' }}">
    <i class="fas fa-user-friends mr-4"></i>
    <span class="nav-text">Family Links</span>
    @php
        $pendingFamilyLinksCount = \App\Models\PropertyFamilyLink::where('status', 'pending')->count();
    @endphp
    @if($pendingFamilyLinksCount > 0)
        <span class="ml-auto bg-yellow-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
            {{ $pendingFamilyLinksCount > 9 ? '9+' : $pendingFamilyLinksCount }}
        </span>
    @endif
</a>

<a href="{{ route('property-units.index') }}" class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('property-units.*') ? 'active' : '' }}">
    <i class="fas fa-door-closed mr-4"></i>
    <span class="nav-text">Property Units</span>
</a>

<button id="administrativeToolsBtn" class="nav-item flex items-center py-2 px-6 w-full text-left hover:bg-opacity-20 transition-colors duration-200 {{
    request()->routeIs('admin.security-posts.*') ||
    request()->routeIs('admin.security-shifts.*') ||
    request()->routeIs('admin.security-schedules.*') ||
    request()->routeIs('admin.supervisor-assignments.*') ||
    request()->routeIs('admin.security-reports.*') ||
    request()->routeIs('admin.ownership-transfers.*') ? 'active' : ''
}}" style="color: var(--sidebar-text);">
    <i class="fas fa-tools mr-4"></i>
    <span class="nav-text flex-grow">Administrative Tools</span>
    @if($pendingOwnershipTransfers > 0 || $pendingSupervisorAssignments > 0)
        <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
            {{ $pendingOwnershipTransfers + $pendingSupervisorAssignments }}
        </span>
    @else
        <i class="fas fa-chevron-right ml-auto text-xs opacity-70"></i>
    @endif
</button>

    {{-- ============================================ --}}
    {{-- 🧹 SANITATION MANAGEMENT --}}
    {{-- ============================================ --}}
    @if($isSuperAdmin || $isAdmin || $isDeveloper)
    <div class="nav-divider mt-1">
        <span class="menu-text">SANITATION MANAGEMENT</span>
    </div>

    <a href="{{ route('sanitation.personnel.index') }}"
       class="nav-item flex items-center py-2 px-6 {{ request()->routeIs('sanitation.personnel.*') ? 'active' : '' }}">
        <i class="fas fa-users mr-4" style="color: var(--primary);"></i>
        <span class="nav-text">Personnel</span>
        @php
            $personnelCount = \App\Models\SanitationPersonnel::count();
        @endphp
        @if($personnelCount > 0)
            <span class="ml-auto bg-blue-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                {{ $personnelCount > 9 ? '9+' : $personnelCount }}
            </span>
        @endif
    </a>
    @endif

</nav>
    @else
        <!-- Unauthorized Access Message -->
        <div class="p-6 text-center">
            <i class="fas fa-exclamation-triangle text-yellow-500 text-3xl mb-3"></i>
            <p class="text-gray-600">Access restricted to administrators only.</p>
            <p class="text-sm text-gray-500 mt-2">Please contact system administrator for access.</p>
            @if(config('app.debug'))
                <div class="mt-4 p-3 rounded-lg text-left text-xs" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <p><strong class="text-red-500">Debug Info:</strong></p>
                    <p class="mt-1">User Type: {{ $currentUserType ?? 'null' }}</p>
                    <p>User Roles: {{ implode(', ', $user->roles->pluck('slug')->toArray()) }}</p>
                    <p>Has Super Admin Access: {{ $hasSuperAdminAccess ? 'Yes' : 'No' }}</p>
                    <p>Has Admin Access: {{ $hasAdminAccess ? 'Yes' : 'No' }}</p>
                    <p>Has Developer Access: {{ $hasDeveloperAccess ? 'Yes' : 'No' }}</p>
                    <p>Session Role: {{ session('selected_role') ?? 'none' }}</p>
                    <p>Current Route: {{ Route::currentRouteName() ?? 'unknown' }}</p>
                </div>
            @endif
        </div>
    @endif

    <!-- Settings Button -->
    @if($isAuthorized)
    <div class="absolute bottom-0 w-full p-3">
        <button id="themeSettingsButton" class="nav-item flex items-center py-2 px-6 w-full justify-center" title="Theme Settings">
            <i class="fas fa-cog text-xl theme-settings-gear" id="settingsIcon"></i>
        </button>
    </div>
    @endif
</div>

{{-- ============================================================ --}}
{{-- ✅ GLOBAL SEARCH SCRIPT BLOCK                                 --}}
{{-- ⚠️ CRITICAL: push to 'search-scripts' (matches the layout's  --}}
{{-- @stack('search-scripts')). Do NOT change to 'scripts'.       --}}
{{-- ============================================================ --}}
@if($isAuthorized)
@push('search-scripts')
<script>
    // ============================================================
    // 1. Alpine factories — registered as early as possible.
    // ============================================================
    (function () {
        function registerSearchFactories() {
            if (typeof Alpine === 'undefined') {
                return false;
            }
            if (Alpine.__searchFactoriesRegistered) {
                return true;
            }

            // --------------------------------------------------------
            // quickSearch — header inline autocomplete
            // --------------------------------------------------------
            Alpine.data('quickSearch', () => ({
                query: '',
                results: {},
                loading: false,
                open: false,
                controller: null,
                category: 'all',

                // ✅ NEW: keyboard navigation
                activeIndex: -1,
                flatItems: [],

                // ✅ NEW: client-side cache so re-typing a prefix is instant
                _cache: new Map(),
                _cacheTTL: 60000, // 60s

                suggestUrl: @json(route('admin.search.suggest')),
                fullUrl:    @json(route('admin.search.index')),

                isEmpty() {
                    return Object.values(this.results).every(arr => !arr || arr.length === 0);
                },

                // ✅ NEW: flatten results for keyboard navigation
                rebuildFlat() {
                    const flat = [];
                    for (const cat of Object.keys(this.results)) {
                        for (const item of (this.results[cat] || [])) {
                            flat.push({ category: cat, ...item });
                        }
                    }
                    this.flatItems = flat;
                    if (this.activeIndex >= flat.length) {
                        this.activeIndex = flat.length - 1;
                    }
                },

                // ✅ NEW: build cache key
                _key(q, c) {
                    return (c || 'all') + '::' + (q || '').toLowerCase();
                },

                // ✅ NEW: try cache first, then network
                async suggest() {
                    const q = (this.query || '').trim();
                    if (q.length < 2) {
                        this.results = {};
                        this.flatItems = [];
                        this.activeIndex = -1;
                        this.open = false;
                        return;
                    }

                    const key = this._key(q, this.category);

                    // Cache hit → instant
                    const cached = this._cache.get(key);
                    if (cached && (Date.now() - cached.t) < this._cacheTTL) {
                        this.results = cached.data;
                        this.rebuildFlat();
                        this.open = true;
                        return;
                    }

                    // Abort any in-flight request
                    if (this.controller) this.controller.abort();
                    this.controller = new AbortController();

                    this.loading = true;
                    this.open = true;

                    try {
                        const params = new URLSearchParams({
                            q: q,
                            category: this.category,
                        });
                        const res = await fetch(`${this.suggestUrl}?${params}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin',
                            signal: this.controller.signal,
                        });
                        if (res.ok) {
                            const data = await res.json();
                            this.results = data.results || {};
                            this.rebuildFlat();

                            // Cache successful response
                            this._cache.set(key, { t: Date.now(), data: this.results });

                            // Keep cache bounded (max 40 entries)
                            if (this._cache.size > 40) {
                                const firstKey = this._cache.keys().next().value;
                                this._cache.delete(firstKey);
                            }
                        }
                    } catch (e) {
                        if (e.name !== 'AbortError') {
                            console.error('Search suggest error:', e);
                        }
                    } finally {
                        this.loading = false;
                    }
                },

                // ✅ NEW: keyboard handler
                onKeydown(e) {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        if (!this.open) { this.open = true; this.suggest(); return; }
                        this.activeIndex = Math.min(this.activeIndex + 1, this.flatItems.length - 1);
                        this._scrollActiveIntoView();
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        this.activeIndex = Math.max(this.activeIndex - 1, -1);
                        this._scrollActiveIntoView();
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        if (this.activeIndex >= 0 && this.flatItems[this.activeIndex]) {
                            const item = this.flatItems[this.activeIndex];
                            this.saveRecent(this.query, this.category);
                            window.location.assign(item.url);
                        } else {
                            this.goToFullResults();
                        }
                    } else if (e.key === 'Escape') {
                        this.close();
                    }
                },

                _scrollActiveIntoView() {
                    this.$nextTick(() => {
                        const el = document.querySelector('[data-search-active="true"]');
                        if (el && el.scrollIntoView) {
                            el.scrollIntoView({ block: 'nearest' });
                        }
                    });
                },

                goToFullResults() {
                    if (this.query.length < 2) return;
                    this.saveRecent(this.query, this.category);

                    // ✅ FIX: close the advanced modal if it happens to be open
                    if (typeof window.closeSearchModal === 'function') {
                        window.closeSearchModal();
                    }

                    const params = new URLSearchParams({ q: this.query, category: this.category });
                    window.location.assign(`${this.fullUrl}?${params}`);
                },

                close() {
                    this.open = false;
                    this.activeIndex = -1;
                },

                getStorageKey() {
                    return 'admin_search_recent_' + @json($currentUserType);
                },

                saveRecent(term, category) {
                    if (!term || term.length < 2) return;
                    try {
                        let list = JSON.parse(localStorage.getItem(this.getStorageKey()) || '[]');
                        list = list.filter(item => !(item.q === term && item.c === category));
                        list.unshift({ q: term, c: category, t: Date.now() });
                        list = list.slice(0, 8);
                        localStorage.setItem(this.getStorageKey(), JSON.stringify(list));
                        document.dispatchEvent(new CustomEvent('search-saved'));
                    } catch (e) {}
                },

                getRecent() {
                    try {
                        return JSON.parse(localStorage.getItem(this.getStorageKey()) || '[]');
                    } catch (e) {
                        return [];
                    }
                },

                clearRecent() {
                    localStorage.removeItem(this.getStorageKey());
                }
            }));

            // --------------------------------------------------------
            // recentSearches — chips in the advanced modal
            // --------------------------------------------------------
            Alpine.data('recentSearches', () => ({
                items: [],

                init() {
                    this.load();
                    window.addEventListener('storage', () => this.load());
                    document.addEventListener('search-saved', () => this.load());
                },

                load() {
                    const key = 'admin_search_recent_' + @json($currentUserType);
                    try {
                        this.items = JSON.parse(localStorage.getItem(key) || '[]');
                    } catch (e) {
                        this.items = [];
                    }
                },

                clearAll() {
                    const key = 'admin_search_recent_' + @json($currentUserType);
                    localStorage.removeItem(key);
                    this.items = [];
                },

                apply(item) {
                    const input  = document.getElementById('globalSearchInput');
                    const select = document.getElementById('searchCategory');
                    if (input)  input.value  = item.q;
                    if (select) select.value = item.c || 'all';

                    // ✅ FIX: close the modal before navigating
                    if (typeof window.closeSearchModal === 'function') {
                        window.closeSearchModal();
                    }

                    setTimeout(() => {
                        document.getElementById('performSearch')?.click();
                    }, 10);
                }
            }));

            Alpine.__searchFactoriesRegistered = true;
            console.log('✅ Search factories registered');
            return true;
        }

        if (!registerSearchFactories()) {
            let attempts = 0;
            const iv = setInterval(function () {
                attempts++;
                if (registerSearchFactories() || attempts > 50) {
                    clearInterval(iv);
                }
            }, 50);
        }

        document.addEventListener('alpine:init', registerSearchFactories);
    })();

    // ============================================================
    // 2. Advanced search modal wiring (non-Alpine)
    // ============================================================
    document.addEventListener('DOMContentLoaded', function () {
        const performBtn = document.getElementById('performSearch');
        const input      = document.getElementById('globalSearchInput');
        const category   = document.getElementById('searchCategory');
        const clearBtn   = document.getElementById('clearSearch');
        const cancelBtn  = document.getElementById('cancelSearch');
        const closeBtn   = document.getElementById('closeSearchModal');
        const modal      = document.getElementById('searchModal');

        // ✅ FIX: robust open/close helpers
        function closeSearchModal() {
            const m = document.getElementById('searchModal');
            if (!m) return;
            m.classList.add('hidden');
            m.style.display = 'none';
            m.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }
        window.closeSearchModal = closeSearchModal;

        function openSearchModal() {
            const m = document.getElementById('searchModal');
            if (!m) return;
            m.classList.remove('hidden');
            m.style.display = 'flex';
            m.setAttribute('aria-hidden', 'false');
        }
        window.openSearchModal = openSearchModal;

        // ✅ FIX: close the modal FIRST, then navigate
        function triggerSearch() {
            const q = (input?.value || '').trim();
            if (q.length < 2) { input?.focus(); return; }

            try {
                const key = 'admin_search_recent_' + @json($currentUserType);
                let list = JSON.parse(localStorage.getItem(key) || '[]');
                list = list.filter(i => !(i.q === q && i.c === (category?.value || 'all')));
                list.unshift({ q: q, c: category?.value || 'all', t: Date.now() });
                list = list.slice(0, 8);
                localStorage.setItem(key, JSON.stringify(list));
                document.dispatchEvent(new CustomEvent('search-saved'));
            } catch (e) {}

            closeSearchModal();

            const url = @json(route('admin.search.index'))
                + '?q=' + encodeURIComponent(q)
                + '&category=' + encodeURIComponent(category?.value || 'all');

            window.location.assign(url);
        }

        if (performBtn) {
            performBtn.addEventListener('click', function (e) {
                e.preventDefault();
                triggerSearch();
            });
        }

        if (input) {
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    triggerSearch();
                }
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function (e) {
                e.preventDefault();
                if (input) input.value = '';
                if (category) category.value = 'all';
                input?.focus();
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function (e) {
                e.preventDefault();
                closeSearchModal();
            });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                closeSearchModal();
            });
        }

        const advancedBtn = document.getElementById('advancedSearchBtn');
        if (advancedBtn && modal) {
            advancedBtn.addEventListener('click', function (e) {
                e.preventDefault();
                openSearchModal();
                setTimeout(() => input?.focus(), 50);
            });
        }

        // ✅ NEW: Esc closes modal, "/" focuses quick search
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                const m = document.getElementById('searchModal');
                if (m && !m.classList.contains('hidden')) {
                    e.preventDefault();
                    closeSearchModal();
                    return;
                }
            }
            if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
                e.preventDefault();
                document.getElementById('quickSearchInput')?.focus();
            }
        });

        // ✅ NEW: backdrop click closes modal
        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeSearchModal();
            });
        }
    });

    // ============================================================
    // 3. ✅ FIX: handle bfcache restore (Back button)
    // ============================================================
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            const m = document.getElementById('searchModal');
            if (m) {
                m.classList.add('hidden');
                m.style.display = 'none';
            }
        }
    });
</script>
@endpush
@endif


<!-- ============ BILLING MANAGEMENT MODAL (Super Admin only) ============ -->
@if($isSuperAdmin)
<div id="billingManagementModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden overflow-y-auto" style="padding-top: 2rem; padding-bottom: 2rem;">
    <div class="billing-modal relative mx-auto my-auto"
         style="background-color: var(--card-bg);
                border: 1px solid var(--border-color);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: modalSlideUp 0.3s ease-out;
                width: 95%;
                max-width: 1200px;
                max-height: calc(100vh - 4rem);
                display: flex;
                flex-direction: column;
                border-radius: 16px;
                overflow: hidden;">

        <div class="modal-header flex justify-between items-center p-6 border-b flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--card-bg);">
            <div>
                <h3 class="text-xl font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-line mr-3" style="color: var(--primary);"></i>
                    Billing Management
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Manage all billing activities, agreements, payments, and reports for your account
                </p>
            </div>
            <button id="closeBillingModal"
                    class="p-2 rounded-full transition-colors duration-200 hover:bg-opacity-20"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <div class="modal-body p-6 overflow-y-auto" style="max-height: calc(100vh - 12rem); scroll-behavior: smooth;">

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="stat-card p-4 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $totalBillingRecords ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Total Agreements</div>
                </div>
                <div class="stat-card p-4 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #22c55e;">{{ $completedPayments ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Completed Payments</div>
                </div>
                <div class="stat-card p-4 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #eab308;">{{ $pendingAgreements ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Pending Agreements</div>
                </div>
                <div class="stat-card p-4 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #ef4444;">{{ $awaitingSignature ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Awaiting Signature</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <a href="{{ route('superadmin.billing.dashboard') }}"
                   class="billing-option-card group block p-5 rounded-xl transition-all duration-300 hover:shadow-lg"
                   style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"
                   onclick="closeBillingModal()">
                    <div class="flex items-start">
                        <div class="billing-icon-container w-12 h-12 rounded-xl flex items-center justify-center mr-4 flex-shrink-0"
                             style="background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.15) 0%, rgba(var(--secondary-rgb), 0.1) 100%);">
                            <i class="fas fa-chart-line text-xl" style="color: var(--primary);"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="billing-title font-semibold text-base" style="color: var(--text-primary);">Billing Dashboard</h4>
                            <p class="billing-description text-sm" style="color: var(--text-secondary);">Overview of all billing activities and metrics</p>
                            <div class="billing-stats mt-2 flex flex-wrap gap-2">
                                <span class="stat-badge text-xs px-2 py-1 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    {{ $totalBillingRecords ?? 0 }} agreements
                                </span>
                                @if(($pendingAgreements ?? 0) > 0)
                                    <span class="stat-badge text-xs px-2 py-1 rounded-full" style="background-color: rgba(239, 68, 68, 0.1); color: #ef4444;">
                                        {{ $pendingAgreements ?? 0 }} pending
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="billing-arrow ml-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                        </div>
                    </div>
                </a>

                <a href="{{ route('superadmin.billing.agreements-list') }}"
                   class="billing-option-card group block p-5 rounded-xl transition-all duration-300 hover:shadow-lg"
                   style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"
                   onclick="closeBillingModal()">
                    <div class="flex items-start">
                        <div class="billing-icon-container w-12 h-12 rounded-xl flex items-center justify-center mr-4 flex-shrink-0"
                             style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.15) 0%, rgba(74, 222, 128, 0.1) 100%);">
                            <i class="fas fa-file-contract text-xl" style="color: #22c55e;"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="billing-title font-semibold text-base" style="color: var(--text-primary);">My Agreements</h4>
                            <p class="billing-description text-sm" style="color: var(--text-secondary);">View and manage all your billing agreements</p>
                            <div class="billing-stats mt-2 flex flex-wrap gap-2">
                                <span class="stat-badge text-xs px-2 py-1 rounded-full" style="background-color: rgba(34, 197, 94, 0.1); color: #22c55e;">
                                    {{ $totalBillingRecords ?? 0 }} total
                                </span>
                                @if(($pendingAgreements ?? 0) > 0)
                                    <span class="stat-badge text-xs px-2 py-1 rounded-full" style="background-color: rgba(239, 68, 68, 0.1); color: #ef4444;">
                                        {{ $pendingAgreements ?? 0 }} pending
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="billing-arrow ml-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                        </div>
                    </div>
                </a>

                <a href="{{ route('superadmin.billing.reports') }}"
                   class="billing-option-card group block p-5 rounded-xl transition-all duration-300 hover:shadow-lg"
                   style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"
                   onclick="closeBillingModal()">
                    <div class="flex items-start">
                        <div class="billing-icon-container w-12 h-12 rounded-xl flex items-center justify-center mr-4 flex-shrink-0"
                             style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(96, 165, 250, 0.1) 100%);">
                            <i class="fas fa-chart-bar text-xl" style="color: #3b82f6;"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="billing-title font-semibold text-base" style="color: var(--text-primary);">Reports & Analytics</h4>
                            <p class="billing-description text-sm" style="color: var(--text-secondary);">Generate billing reports and export data</p>
                            <div class="billing-stats mt-2 flex flex-wrap gap-2">
                                <span class="stat-badge text-xs px-2 py-1 rounded-full" style="background-color: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                                    <i class="fas fa-download mr-1"></i> Export
                                </span>
                            </div>
                        </div>
                        <div class="billing-arrow ml-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                        </div>
                    </div>
                </a>

                <a href="{{ route('superadmin.billing.payment-history') }}"
                   class="billing-option-card group block p-5 rounded-xl transition-all duration-300 hover:shadow-lg"
                   style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"
                   onclick="closeBillingModal()">
                    <div class="flex items-start">
                        <div class="billing-icon-container w-12 h-12 rounded-xl flex items-center justify-center mr-4 flex-shrink-0"
                             style="background: linear-gradient(135deg, rgba(168, 85, 247, 0.15) 0%, rgba(196, 181, 253, 0.1) 100%);">
                            <i class="fas fa-history text-xl" style="color: #8b5cf6;"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="billing-title font-semibold text-base" style="color: var(--text-primary);">Payment History</h4>
                            <p class="billing-description text-sm" style="color: var(--text-secondary);">View complete payment history and records</p>
                            <div class="billing-stats mt-2 flex flex-wrap gap-2">
                                <span class="stat-badge text-xs px-2 py-1 rounded-full" style="background-color: rgba(168, 85, 247, 0.1); color: #8b5cf6;">
                                    {{ $completedPayments ?? 0 }} payments
                                </span>
                                @if(($pendingPayments ?? 0) > 0)
                                    <span class="stat-badge text-xs px-2 py-1 rounded-full" style="background-color: rgba(239, 68, 68, 0.1); color: #ef4444;">
                                        {{ $pendingPayments ?? 0 }} pending
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="billing-arrow ml-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                        </div>
                    </div>
                </a>

                @if($isPrimary)
                    <a href="{{ route('superadmin.billing.shared-payment-status') }}"
                       class="billing-option-card group block p-5 rounded-xl transition-all duration-300 hover:shadow-lg"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); border-left: 4px solid #f59e0b;"
                       onclick="closeBillingModal()">
                        <div class="flex items-start">
                            <div class="billing-icon-container w-12 h-12 rounded-xl flex items-center justify-center mr-4 flex-shrink-0"
                                 style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(251, 191, 36, 0.1) 100%);">
                                <i class="fas fa-star text-xl" style="color: #f59e0b;"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="billing-title font-semibold text-base" style="color: var(--text-primary);">
                                    Shared Payment Status
                                    <span class="ml-2 text-xs px-2 py-0.5 rounded-full" style="background-color: #f59e0b; color: white;">Primary</span>
                                </h4>
                                <p class="billing-description text-sm" style="color: var(--text-secondary);">View payment status of all super admins</p>
                                <div class="billing-stats mt-2 flex flex-wrap gap-2">
                                    <span class="stat-badge text-xs px-2 py-1 rounded-full" style="background-color: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                                        <i class="fas fa-crown mr-1"></i> Primary Contact
                                    </span>
                                </div>
                            </div>
                            <div class="billing-arrow ml-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                            </div>
                        </div>
                    </a>
                @endif

                <a href="{{ route('superadmin.billing.statistics') }}"
                   class="billing-option-card group block p-5 rounded-xl transition-all duration-300 hover:shadow-lg"
                   style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"
                   onclick="closeBillingModal()">
                    <div class="flex items-start">
                        <div class="billing-icon-container w-12 h-12 rounded-xl flex items-center justify-center mr-4 flex-shrink-0"
                             style="background: linear-gradient(135deg, rgba(236, 72, 153, 0.15) 0%, rgba(244, 114, 182, 0.1) 100%);">
                            <i class="fas fa-chart-pie text-xl" style="color: #ec4899;"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="billing-title font-semibold text-base" style="color: var(--text-primary);">Billing Statistics</h4>
                            <p class="billing-description text-sm" style="color: var(--text-secondary);">View detailed billing analytics and trends</p>
                            <div class="billing-stats mt-2 flex flex-wrap gap-2">
                                <span class="stat-badge text-xs px-2 py-1 rounded-full" style="background-color: rgba(236, 72, 153, 0.1); color: #ec4899;">
                                    <i class="fas fa-chart-line mr-1"></i> Analytics
                                </span>
                            </div>
                        </div>
                        <div class="billing-arrow ml-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                        </div>
                    </div>
                </a>

                <a href="{{ route('superadmin.billing.export') }}"
                   class="billing-option-card group block p-5 rounded-xl transition-all duration-300 hover:shadow-lg"
                   style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"
                   onclick="closeBillingModal()">
                    <div class="flex items-start">
                        <div class="billing-icon-container w-12 h-12 rounded-xl flex items-center justify-center mr-4 flex-shrink-0"
                             style="background: linear-gradient(135deg, rgba(20, 184, 166, 0.15) 0%, rgba(45, 212, 191, 0.1) 100%);">
                            <i class="fas fa-file-export text-xl" style="color: #14b8a6;"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="billing-title font-semibold text-base" style="color: var(--text-primary);">Export Data</h4>
                            <p class="billing-description text-sm" style="color: var(--text-secondary);">Export billing data in CSV format</p>
                            <div class="billing-stats mt-2 flex flex-wrap gap-2">
                                <span class="stat-badge text-xs px-2 py-1 rounded-full" style="background-color: rgba(20, 184, 166, 0.1); color: #14b8a6;">
                                    <i class="fas fa-download mr-1"></i> CSV Export
                                </span>
                            </div>
                        </div>
                        <div class="billing-arrow ml-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                        </div>
                    </div>
                </a>
            </div>

            <div class="mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                <h4 class="text-sm font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i>Quick Actions
                </h4>
                <div class="flex flex-wrap gap-3">
                    @if($isSuperAdmin)
                        <a href="{{ route('superadmin.billing.agreements-list') }}"
                           class="quick-action-btn px-4 py-2 rounded-lg text-sm transition-colors duration-200"
                           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                           onclick="closeBillingModal()">
                            <i class="fas fa-list mr-2"></i>View All Agreements
                        </a>
                        <a href="{{ route('superadmin.billing.payment-history') }}"
                           class="quick-action-btn px-4 py-2 rounded-lg text-sm transition-colors duration-200"
                           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                           onclick="closeBillingModal()">
                            <i class="fas fa-credit-card mr-2"></i>Payment History
                        </a>
                        <a href="{{ route('superadmin.billing.reports') }}"
                           class="quick-action-btn px-4 py-2 rounded-lg text-sm transition-colors duration-200"
                           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                           onclick="closeBillingModal()">
                            <i class="fas fa-file-export mr-2"></i>Export Reports
                        </a>
                        @if($isPrimary)
                            <a href="{{ route('superadmin.billing.shared-payment-status') }}"
                               class="quick-action-btn px-4 py-2 rounded-lg text-sm transition-colors duration-200"
                               style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                               onclick="closeBillingModal()">
                                <i class="fas fa-users-cog mr-2"></i>Shared Status
                            </a>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <div class="modal-footer p-6 border-t flex justify-end flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--bg-secondary);">
            <button id="cancelBillingModal"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200"
                    style="color: var(--text-secondary);">
                Close
            </button>
        </div>
    </div>
</div>
@endif

<!-- ============ EMAIL ACCOUNT MANAGEMENT MODAL ============ -->
@if($isAuthorized)
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
                    @if($isSuperAdmin)
                        Manage your email accounts
                    @elseif($isAdmin)
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

        <div class="modal-body p-6 overflow-y-auto" style="max-height: calc(100vh - 12rem); scroll-behavior: smooth;">

            @php
                $superAdmin = \App\Models\User::where('type', 0)->first();

                if ($isSuperAdmin) {
                    $emailAccounts = auth()->user()->emailAccounts()->get();
                    $userNames = [];
                } elseif ($isAdmin) {
                    if ($superAdmin) {
                        $emailAccounts = $superAdmin->emailAccounts()->get();
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
                        // Skip
                    }
                }
            @endphp

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

            @if($isAdmin)
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

            <div class="mb-6">
                <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i>Quick Actions
                </h4>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    @if($isSuperAdmin)
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

            @if($totalAccounts > 0)
                <div>
                    <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-list mr-2" style="color: var(--primary);"></i>
                        @if($isSuperAdmin)
                            Your Email Accounts
                        @elseif($isAdmin)
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
                                            @if($isAdmin)
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
                                    @if($isSuperAdmin && !$account->is_primary && $account->status === 'verified')
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
                                    @if($isSuperAdmin)
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
                                    @if($isSuperAdmin)
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
                        @if($isAdmin)
                            No email accounts have been linked by Super Admin yet.
                            <br>Please contact your Super Admin to set up email accounts.
                        @elseif($isSuperAdmin)
                            You haven't linked any email accounts yet.
                        @else
                            You haven't linked any email accounts yet.
                        @endif
                    </p>
                    @if($isSuperAdmin)
                        <a href="{{ route('email-accounts.create') }}" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200"
                           style="background-color: var(--primary); color: white;"
                           onclick="closeEmailModal()">
                            <i class="fas fa-plus mr-2"></i>Link Your First Account
                        </a>
                    @endif
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

<!-- ============ SMS MANAGEMENT MODAL ============ -->
@if($isAuthorized && $smsProviderConfigured)
<div id="smsManagementModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden overflow-y-auto" style="padding-top: 2rem; padding-bottom: 2rem;">
    <div class="sms-modal relative mx-auto my-auto"
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
                    <i class="fas fa-sms mr-3" style="color: var(--primary);"></i>
                    SMS Management
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Manage SMS providers, send messages, and monitor SMS activity
                </p>
            </div>
            <button id="closeSmsModal"
                    class="p-2 rounded-full transition-colors duration-200 hover:bg-opacity-20"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <div class="modal-body p-6 overflow-y-auto" style="max-height: calc(100vh - 12rem); scroll-behavior: smooth;">

            @if($smsQuickStatus['system_ready'] ?? false)
                <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(34, 197, 94, 0.1); border: 1px solid #22c55e;">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-3 text-lg"></i>
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">SMS System Operational</p>
                            <p class="text-sm" style="color: var(--text-secondary);">{{ $smsSystemStatus['status_message'] ?? 'SMS service is ready to send messages' }}</p>
                        </div>
                    </div>
                </div>
            @else
                <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444;">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3 text-lg"></i>
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">SMS System Not Ready</p>
                            <p class="text-sm" style="color: var(--text-secondary);">{{ $smsSystemStatus['status_message'] ?? 'Please configure an SMS provider' }}</p>
                            @if($isSuperAdmin || $isDeveloper)
                                <a href="{{ route('admin.sms-providers.index') }}" class="text-sm mt-2 inline-block px-3 py-1 rounded" style="background-color: var(--primary); color: white;" onclick="closeSmsModal()">
                                    <i class="fas fa-cog mr-1"></i> Configure Providers
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 mb-6">
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $smsUsageStats['total_sent'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Total Sent</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #22c55e;">{{ $smsUsageStats['successful'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Successful</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #ef4444;">{{ $smsUsageStats['failed'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Failed</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #eab308;">{{ $smsUsageStats['success_rate'] ?? 0 }}%</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Success Rate</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $smsUsageStats['today'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Today</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $smsUsageStats['this_month'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">This Month</div>
                </div>
            </div>

            <div class="mb-6">
                <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i>Quick Actions
                </h4>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    <button onclick="openSmsCompose()" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                            style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, var(--primary), var(--secondary));">
                            <i class="fas fa-pen text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Send SMS</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Compose new message</div>
                        </div>
                    </button>

                    <a href="{{ route('sms.logs') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeSmsModal()">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);">
                            <i class="fas fa-history text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">View Logs</div>
                            <div class="text-xs" style="color: var(--text-secondary);">SMS history</div>
                        </div>
                    </a>

                    @if($isSuperAdmin || $isDeveloper)
                        <a href="{{ route('admin.sms-providers.index') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                           onclick="closeSmsModal()">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                                <i class="fas fa-cog text-white text-xs"></i>
                            </div>
                            <div>
                                <div class="text-sm font-medium">Providers</div>
                                <div class="text-xs" style="color: var(--text-secondary);">Configure SMS providers</div>
                            </div>
                        </a>
                    @endif

                    <button onclick="testSmsConnection()" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                            style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #06b6d4, #22d3ee);">
                            <i class="fas fa-wifi text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Test Connection</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Verify SMS provider</div>
                        </div>
                    </button>
                </div>
            </div>

            @if(!empty($smsProviders))
                <div class="mb-6">
                    <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-server mr-2" style="color: var(--primary);"></i>Provider Status
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($smsProviders as $key => $provider)
                            <div class="provider-card p-3 rounded-lg transition-all duration-200"
                                 style="background-color: var(--bg-secondary); border: 1px solid {{ ($provider['enabled'] ?? false) && ($provider['configured'] ?? false) ? 'var(--success)' : 'var(--border-color)' }};">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-2 h-2 rounded-full mr-2 {{ ($provider['enabled'] ?? false) && ($provider['configured'] ?? false) ? 'bg-green-500' : 'bg-red-500' }}"></div>
                                        <span class="font-medium" style="color: var(--text-primary);">{{ $provider['name'] ?? $key }}</span>
                                    </div>
                                    <span class="text-xs px-2 py-0.5 rounded-full {{ ($provider['enabled'] ?? false) && ($provider['configured'] ?? false) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ ($provider['enabled'] ?? false) && ($provider['configured'] ?? false) ? 'Ready' : (($provider['enabled'] ?? false) ? 'Misconfigured' : 'Disabled') }}
                                    </span>
                                </div>
                                @if(isset($provider['missing_configuration']) && !empty($provider['missing_configuration']))
                                    <div class="text-xs mt-1" style="color: var(--danger);">
                                        <i class="fas fa-exclamation-circle mr-1"></i>
                                        Missing: {{ implode(', ', $provider['missing_configuration']) }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @php
                $recentSms = [];
                try {
                    $recentSms = \App\Models\SmsLog::orderBy('created_at', 'desc')
                        ->limit(10)
                        ->get();
                } catch (\Exception $e) {
                    $recentSms = [];
                }
            @endphp

            @if($recentSms->isNotEmpty())
                <div>
                    <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-clock mr-2" style="color: var(--primary);"></i>Recent Activity
                    </h4>
                    <div class="space-y-2">
                        @foreach($recentSms as $log)
                            <div class="flex items-center justify-between p-3 rounded-lg transition-all duration-200"
                                 style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="flex items-center min-w-0 flex-1">
                                    <div class="w-2 h-2 rounded-full mr-3 flex-shrink-0 {{ $log->status === 'success' ? 'bg-green-500' : 'bg-red-500' }}"></div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm truncate" style="color: var(--text-primary);">
                                            {{ Str::limit($log->message ?? 'No message', 50) }}
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            <span>{{ $log->provider ?? 'unknown' }}</span>
                                            <span class="mx-1">•</span>
                                            <span>{{ $log->phone_number ?? 'unknown' }}</span>
                                            <span class="mx-1">•</span>
                                            <span>{{ $log->created_at ? $log->created_at->diffForHumans() : 'N/A' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center flex-shrink-0 ml-2">
                                    <span class="text-xs px-2 py-0.5 rounded-full {{ $log->status === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ ucfirst($log->status ?? 'unknown') }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="modal-footer p-4 border-t flex justify-between flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--bg-secondary);">
            <div>
                <button onclick="refreshSmsStatus()" class="text-sm px-3 py-1.5 rounded-lg transition-colors duration-200"
                        style="background-color: var(--primary); color: white;">
                    <i class="fas fa-sync mr-1"></i> Refresh Status
                </button>
            </div>
            <button id="cancelSmsModal"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200"
                    style="color: var(--text-secondary);">
                Close
            </button>
        </div>
    </div>
</div>
@endif

<!-- ============ WHATSAPP MANAGEMENT MODAL ============ -->
@if($isAuthorized && $whatsappProviderConfigured)
<div id="whatsappManagementModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden overflow-y-auto" style="padding-top: 2rem; padding-bottom: 2rem;">
    <div class="whatsapp-modal relative mx-auto my-auto"
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
                    <i class="fab fa-whatsapp mr-3" style="color: #25D366;"></i>
                    WhatsApp Management
                </h3>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Manage WhatsApp Business API providers, send messages, templates, and monitor activity
                </p>
            </div>
            <button id="closeWhatsAppModal"
                    class="p-2 rounded-full transition-colors duration-200 hover:bg-opacity-20"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <div class="modal-body p-6 overflow-y-auto" style="max-height: calc(100vh - 12rem); scroll-behavior: smooth;">

            @if($whatsappQuickStatus['system_ready'] ?? false)
                <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(37, 211, 102, 0.1); border: 1px solid #25D366;">
                    <div class="flex items-center">
                        <i class="fab fa-whatsapp text-green-500 mr-3 text-lg"></i>
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">WhatsApp System Operational</p>
                            <p class="text-sm" style="color: var(--text-secondary);">{{ $whatsappSystemStatus['status_message'] ?? 'WhatsApp service is ready to send messages' }}</p>
                        </div>
                    </div>
                </div>
            @else
                <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444;">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3 text-lg"></i>
                        <div>
                            <p class="font-medium" style="color: var(--text-primary);">WhatsApp System Not Ready</p>
                            <p class="text-sm" style="color: var(--text-secondary);">{{ $whatsappSystemStatus['status_message'] ?? 'Please configure a WhatsApp provider' }}</p>
                            @if($isSuperAdmin || $isDeveloper)
                                <a href="{{ route('admin.whatsapp-providers.index') }}" class="text-sm mt-2 inline-block px-3 py-1 rounded" style="background-color: #25D366; color: white;" onclick="closeWhatsAppModal()">
                                    <i class="fab fa-whatsapp mr-1"></i> Configure Providers
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #25D366;">{{ $totalWhatsAppProviders ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Total Providers</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #22c55e;">0</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Messages Sent</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: #eab308;">0</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Templates</div>
                </div>
                <div class="stat-card p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <div class="text-2xl font-bold" style="color: var(--primary);">0</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Webhooks</div>
                </div>
            </div>

            <div class="mb-6">
                <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i>Quick Actions
                </h4>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    <button onclick="openWhatsAppCompose()" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                            style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #25D366, #128C7E);">
                            <i class="fab fa-whatsapp text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Send Message</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Compose new WhatsApp</div>
                        </div>
                    </button>

                    <a href="{{ route('admin.whatsapp.logs.index') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeWhatsAppModal()">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #3b82f6, #60a5fa);">
                            <i class="fas fa-history text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">View Logs</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Message history</div>
                        </div>
                    </a>

                    <a href="{{ route('admin.whatsapp.templates.index') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeWhatsAppModal()">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                            <i class="fas fa-file-alt text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Templates</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Manage message templates</div>
                        </div>
                    </a>

                    @if($isSuperAdmin || $isDeveloper)
                        <a href="{{ route('admin.whatsapp-providers.index') }}" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                           onclick="closeWhatsAppModal()">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                                <i class="fas fa-cog text-white text-xs"></i>
                            </div>
                            <div>
                                <div class="text-sm font-medium">Providers</div>
                                <div class="text-xs" style="color: var(--text-secondary);">Configure WhatsApp</div>
                            </div>
                        </a>
                    @endif

                    <button onclick="testWhatsAppConnection()" class="quick-action-btn group flex items-center p-3 rounded-lg transition-all duration-200"
                            style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: linear-gradient(135deg, #06b6d4, #22d3ee);">
                            <i class="fas fa-wifi text-white text-xs"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium">Test Connection</div>
                            <div class="text-xs" style="color: var(--text-secondary);">Verify WhatsApp provider</div>
                        </div>
                    </button>
                </div>
            </div>

            @if(!empty($whatsappProviders))
                <div>
                    <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                        <i class="fas fa-server mr-2" style="color: var(--primary);"></i>Provider Status
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($whatsappProviders as $key => $provider)
                            <div class="provider-card p-3 rounded-lg transition-all duration-200"
                                 style="background-color: var(--bg-secondary); border: 1px solid {{ ($provider['enabled'] ?? false) && ($provider['configured'] ?? false) ? 'var(--success)' : 'var(--border-color)' }};">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-2 h-2 rounded-full mr-2 {{ ($provider['enabled'] ?? false) && ($provider['configured'] ?? false) ? 'bg-green-500' : 'bg-red-500' }}"></div>
                                        <span class="font-medium" style="color: var(--text-primary);">{{ $provider['name'] ?? $key }}</span>
                                    </div>
                                    <span class="text-xs px-2 py-0.5 rounded-full {{ ($provider['enabled'] ?? false) && ($provider['configured'] ?? false) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                        {{ ($provider['enabled'] ?? false) && ($provider['configured'] ?? false) ? 'Ready' : (($provider['enabled'] ?? false) ? 'Misconfigured' : 'Disabled') }}
                                    </span>
                                </div>
                                @if(isset($provider['missing_configuration']) && !empty($provider['missing_configuration']))
                                    <div class="text-xs mt-1" style="color: var(--danger);">
                                        <i class="fas fa-exclamation-circle mr-1"></i>
                                        Missing: {{ implode(', ', $provider['missing_configuration']) }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="modal-footer p-4 border-t flex justify-between flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--bg-secondary);">
            <div>
                <button onclick="refreshWhatsAppStatus()" class="text-sm px-3 py-1.5 rounded-lg transition-colors duration-200"
                        style="background-color: #25D366; color: white;">
                    <i class="fas fa-sync mr-1"></i> Refresh Status
                </button>
            </div>
            <button id="cancelWhatsAppModal"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200"
                    style="color: var(--text-secondary);">
                Close
            </button>
        </div>
    </div>
</div>
@endif

{{-- ============================================ --}}
{{-- ✅ SEARCH MODAL — Advanced search --}}
{{-- ============================================ --}}
@if($isAuthorized)
<div id="searchModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden overflow-y-auto" style="padding-top: 5rem; padding-bottom: 2rem;" aria-hidden="true">
    <div class="rounded-lg shadow-xl w-11/12 md:w-2/3 lg:w-1/2 max-w-2xl search-modal-container relative mx-auto my-auto"
         style="background-color: var(--card-bg);
                border: 1px solid var(--border-color);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: modalSlideIn 0.3s ease-out;
                max-height: calc(100vh - 6rem);
                display: flex;
                flex-direction: column;
                border-radius: 16px;
                overflow: hidden;">

        <!-- Modal Header -->
        <div class="px-6 py-4 border-b flex justify-between items-center flex-shrink-0 modal-header-light"
             style="border-color: var(--border-color); background-color: var(--card-bg);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-search mr-2" style="color: var(--primary);"></i>Advanced Search
                <span class="text-xs ml-2 px-2 py-1 rounded-full"
                      style="background-color: var(--primary); color: white;">
                    @if($isSuperAdmin)
                        Super Admin
                    @elseif($isAdmin)
                        Admin
                    @elseif($isDeveloper)
                        Developer
                    @endif
                </span>
            </h3>
            <button id="closeSearchModal"
                    class="p-1 rounded-full transition-colors duration-200 hover:bg-opacity-20"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="flex-1 overflow-y-auto modal-scrollable-body p-6" style="max-height: calc(100vh - 14rem); scroll-behavior: smooth;">

            <!-- Info Message -->
            <div class="mb-6 p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.1); border: 1px solid var(--primary);">
                <div class="flex items-start">
                    <i class="fas fa-info-circle mr-3 mt-0.5" style="color: var(--primary);"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-primary);">How to Search</p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            Type at least 2 characters. Press <kbd class="px-1.5 py-0.5 rounded text-xs" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">Enter</kbd>
                            to see full results, or click the search button. The header quick-search shows results live as you type.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Search Input -->
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                    Search Term
                </label>
                <input type="text" id="globalSearchInput" placeholder="Enter search term (min 2 characters)..."
                       class="w-full px-4 py-3 rounded-lg transition-colors duration-200"
                       style="background-color: var(--bg-secondary);
                              border: 1px solid var(--border-color);
                              color: var(--text-primary);"
                       data-role="{{ $searchRole }}"
                       autocomplete="off"
                       minlength="2">
            </div>

            <!-- Search Category -->
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                    Search Category (Optional)
                </label>
                <select id="searchCategory"
                        class="w-full px-4 py-3 rounded-lg transition-colors duration-200 appearance-none"
                        style="background-color: var(--bg-secondary);
                               border: 1px solid var(--border-color);
                               color: var(--text-primary);
                               background-image: url(\"data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e\");
                               background-position: right 0.5rem center;
                               background-repeat: no-repeat;
                               background-size: 1.5em 1.5em;
                               padding-right: 2.5rem;">
                    <option value="all">All Categories</option>
                    <option value="users">👥 Users</option>
                    <option value="registration-plans">🗺️ Registration Plans</option>
                    <option value="construction-registrations">🏗️ Construction Registrations</option>
                    <option value="construction-contracts">📐 Construction Contracts</option>
                    <option value="properties">🏢 Properties</option>
                    <option value="property-units">🚪 Property Units</option>
                    <option value="payments">💳 Payments</option>
                    <option value="invoices">📄 Landlord Invoices</option>
                    <option value="tenant-invoices">🧾 Tenant Invoices</option>
                    <option value="security-posts">📍 Security Posts</option>
                    <option value="security-shifts">⏰ Security Shifts</option>
                    <option value="security-schedules">📅 Security Schedules</option>
                    <option value="security-supervisor-assignments">👔 Supervisor Assignments</option>
                    <option value="security-reports">📊 Security Reports</option>
                    <option value="ownership-transfers">🔄 Ownership Transfers</option>
                    @if($isSuperAdmin)
                        <option value="system-settings">⚙️ System Settings</option>
                        <option value="payment-providers">💳 Payment Providers</option>
                        <option value="sms-providers">📱 SMS Providers</option>
                        <option value="whatsapp-providers">💬 WhatsApp Providers</option>
                        <option value="superadmin-billing">💰 Billing Management</option>
                    @endif
                </select>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    <i class="fas fa-lightbulb mr-1"></i> Select a category to search within a specific module
                </p>
            </div>

            {{-- ✅ Recent Searches — Alpine-powered --}}
            <div id="recentSearches" class="mb-6" x-data="recentSearches()" x-init="init()">
                <div class="flex justify-between items-center mb-2">
                    <label class="block text-sm font-medium" style="color: var(--text-primary);">
                        <i class="fas fa-history mr-1"></i> Recent Searches
                    </label>
                    <button type="button"
                            @click="clearAll()"
                            class="text-xs px-2 py-1 rounded transition-colors duration-200"
                            style="color: var(--danger);"
                            title="Clear all recent searches">
                        <i class="fas fa-trash-alt mr-1"></i>Clear All
                    </button>
                </div>

                <div class="flex flex-wrap gap-2">
                    <template x-if="items.length === 0">
                        <span class="text-xs" style="color: var(--text-secondary);">
                            No recent searches yet.
                        </span>
                    </template>

                    <template x-for="item in items" :key="item.q + '-' + item.c">
                        <button type="button"
                                @click="apply(item)"
                                class="px-3 py-1.5 rounded-full text-xs transition-colors duration-200 flex items-center"
                                style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                            <i class="fas fa-search mr-1 text-xs opacity-60"></i>
                            <span x-text="item.q"></span>
                            <span x-show="item.c && item.c !== 'all'"
                                  class="ml-1 text-[10px] px-1.5 py-0.5 rounded-full"
                                  style="background-color: rgba(var(--primary-rgb), 0.15); color: var(--primary);"
                                  x-text="item.c"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <!-- Scroll indicators -->
        <div class="scroll-indicator top-indicator hidden" id="scrollTopIndicator">
            <i class="fas fa-chevron-up" style="color: var(--primary);"></i>
        </div>
        <div class="scroll-indicator bottom-indicator hidden" id="scrollBottomIndicator">
            <i class="fas fa-chevron-down" style="color: var(--primary);"></i>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-4 border-t flex justify-between flex-shrink-0 modal-footer-light"
             style="border-color: var(--border-color); background-color: var(--bg-secondary);">
            <div class="flex space-x-2">
                <button id="clearSearch"
                        type="button"
                        class="px-3 py-2 text-xs font-medium transition-colors duration-200 rounded"
                        style="color: var(--text-secondary);">
                    <i class="fas fa-eraser mr-1"></i>Clear
                </button>
            </div>
            <div class="flex space-x-3">
                <button id="cancelSearch"
                        type="button"
                        class="px-4 py-2 text-sm font-medium transition-colors duration-200 rounded"
                        style="color: var(--text-secondary);">
                    Cancel
                </button>
                <button id="performSearch"
                        type="button"
                        class="px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200"
                        style="background-color: var(--primary); color: white;">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<!-- ============ INVOICE MANAGEMENT MODAL ============ -->
@if($isAuthorized)
<div id="invoiceManagementModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden overflow-y-auto" style="padding-top: 2rem; padding-bottom: 2rem;">
    <div class="invoice-modal relative mx-auto my-auto"
         style="background-color: var(--card-bg);
                border: 1px solid var(--border-color);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: modalSlideUp 0.3s ease-out;
                width: 90%;
                max-width: 1400px;
                max-height: calc(100vh - 4rem);
                display: flex;
                flex-direction: column;
                border-radius: 16px;
                overflow: hidden;">

        <div class="modal-header flex justify-between items-center p-6 border-b flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--card-bg);">
            <h3 class="text-xl font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-file-invoice-dollar mr-3" style="color: var(--primary);"></i>
                Invoice Management
            </h3>
            <button id="closeInvoiceModal"
                    class="p-2 rounded-full transition-colors duration-200 hover:bg-opacity-20"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <div class="modal-body p-6 overflow-y-auto" style="max-height: calc(100vh - 12rem); scroll-behavior: smooth;">
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="w-1 h-8 bg-primary rounded-full mr-3"></div>
                        <h4 class="text-lg font-semibold" style="color: var(--text-primary);">
                            <i class="fas fa-file-invoice mr-2" style="color: var(--primary);"></i>
                            All Invoices
                        </h4>
                    </div>
                    <a href="{{ route('invoices.index') }}"
                       class="px-4 py-2 rounded-lg transition-colors duration-200 text-sm font-medium"
                       style="background-color: var(--primary); color: white;"
                       onclick="closeInvoiceModal()">
                        <i class="fas fa-arrow-right mr-2"></i>
                        View All Invoices
                    </a>
                </div>
                <p class="text-sm mb-6" style="color: var(--text-secondary);">
                    Manage all landlord and tenant invoices, payments, and billing records.
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="invoice-section landlord-section">
                    <div class="section-header flex items-center mb-4 pb-3 border-b" style="border-color: var(--border-color);">
                        <div class="w-1 h-6 bg-blue-500 rounded-full mr-3"></div>
                        <i class="fas fa-building text-blue-500 mr-2 text-lg"></i>
                        <h4 class="text-lg font-semibold" style="color: var(--text-primary);">Landlord Invoices</h4>
                        <div class="ml-auto px-2 py-1 rounded-full text-xs font-medium" style="background-color: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                            Property Owners
                        </div>
                    </div>

                    <div class="space-y-4">
                        <a href="{{ route('invoices.year-end.management') }}"
                           class="invoice-card group block transition-all duration-300 rounded-xl p-5"
                           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"
                           onclick="closeInvoiceModal()">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start space-x-4">
                                    <div class="invoice-icon p-3 rounded-xl" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(147, 197, 253, 0.1) 100%);">
                                        <i class="fas fa-archive text-blue-500 text-xl"></i>
                                    </div>
                                    <div>
                                        <h5 class="font-semibold text-base mb-1" style="color: var(--text-primary);">Year-End Archive</h5>
                                        <p class="text-sm" style="color: var(--text-secondary);">Manage and archive paid invoices from previous years</p>
                                        @if($landlordEligibleForArchive > 0)
                                            <div class="mt-2 flex items-center">
                                                <span class="px-2 py-1 rounded-full text-xs font-medium" style="background-color: rgba(59, 130, 246, 0.2); color: #3b82f6;">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    {{ $landlordEligibleForArchive }} invoice(s) eligible for archive
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="invoice-arrow opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <i class="fas fa-chevron-right text-gray-400"></i>
                                </div>
                            </div>
                        </a>

                        <a href="{{ route('invoices.unpaid-previous-years') }}"
                           class="invoice-card group block transition-all duration-300 rounded-xl p-5"
                           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"
                           onclick="closeInvoiceModal()">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start space-x-4">
                                    <div class="invoice-icon p-3 rounded-xl" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(252, 165, 165, 0.1) 100%);">
                                        <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                                    </div>
                                    <div>
                                        <h5 class="font-semibold text-base mb-1" style="color: var(--text-primary);">Unpaid from Previous Years</h5>
                                        <p class="text-sm" style="color: var(--text-secondary);">Review and manage overdue invoices from prior years</p>
                                        @if($landlordUnpaidPreviousYears > 0)
                                            <div class="mt-2 flex items-center">
                                                <span class="px-2 py-1 rounded-full text-xs font-medium" style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444;">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    {{ $landlordUnpaidPreviousYears }} unpaid invoice(s) from previous years
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="invoice-arrow opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <i class="fas fa-chevron-right text-gray-400"></i>
                                </div>
                            </div>
                        </a>

                        <div class="grid grid-cols-2 gap-3 mt-4">
                            <div class="stat-mini p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="text-2xl font-bold" style="color: var(--primary);">{{ $landlordEligibleForArchive }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">Ready for Archive</div>
                            </div>
                            <div class="stat-mini p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="text-2xl font-bold" style="color: var(--warning);">{{ $landlordUnpaidPreviousYears }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">Unpaid Previous Years</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="invoice-section tenant-section">
                    <div class="section-header flex items-center mb-4 pb-3 border-b" style="border-color: var(--border-color);">
                        <div class="w-1 h-6 bg-green-500 rounded-full mr-3"></div>
                        <i class="fas fa-users text-green-500 mr-2 text-lg"></i>
                        <h4 class="text-lg font-semibold" style="color: var(--text-primary);">Tenant Invoices</h4>
                        <div class="ml-auto px-2 py-1 rounded-full text-xs font-medium" style="background-color: rgba(34, 197, 94, 0.1); color: #22c55e;">
                            Renters
                        </div>
                    </div>

                    <div class="space-y-4">
                        <a href="{{ route('admin.tenant-invoices.year-end-management') }}"
                           class="invoice-card group block transition-all duration-300 rounded-xl p-5"
                           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"
                           onclick="closeInvoiceModal()">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start space-x-4">
                                    <div class="invoice-icon p-3 rounded-xl" style="background: linear-gradient(135deg, rgba(34, 197, 94, 0.1) 0%, rgba(74, 222, 128, 0.1) 100%);">
                                        <i class="fas fa-archive text-green-500 text-xl"></i>
                                    </div>
                                    <div>
                                        <h5 class="font-semibold text-base mb-1" style="color: var(--text-primary);">Year-End Archive</h5>
                                        <p class="text-sm" style="color: var(--text-secondary);">Archive paid tenant invoices from previous years</p>
                                        @if($tenantEligibleForArchive > 0)
                                            <div class="mt-2 flex items-center">
                                                <span class="px-2 py-1 rounded-full text-xs font-medium" style="background-color: rgba(34, 197, 94, 0.2); color: #22c55e;">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    {{ $tenantEligibleForArchive }} invoice(s) eligible for archive
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="invoice-arrow opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <i class="fas fa-chevron-right text-gray-400"></i>
                                </div>
                            </div>
                        </a>

                        <a href="{{ route('admin.tenant-invoices.unpaid-previous-years') }}"
                           class="invoice-card group block transition-all duration-300 rounded-xl p-5"
                           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);"
                           onclick="closeInvoiceModal()">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start space-x-4">
                                    <div class="invoice-icon p-3 rounded-xl" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(252, 165, 165, 0.1) 100%);">
                                        <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                                    </div>
                                    <div>
                                        <h5 class="font-semibold text-base mb-1" style="color: var(--text-primary);">Unpaid from Previous Years</h5>
                                        <p class="text-sm" style="color: var(--text-secondary);">Review overdue tenant invoices from prior years</p>
                                        @if($tenantUnpaidPreviousYears > 0)
                                            <div class="mt-2 flex items-center">
                                                <span class="px-2 py-1 rounded-full text-xs font-medium" style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444;">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    {{ $tenantUnpaidPreviousYears }} unpaid invoice(s) from previous years
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="invoice-arrow opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <i class="fas fa-chevron-right text-gray-400"></i>
                                </div>
                            </div>
                        </a>

                        <div class="grid grid-cols-2 gap-3 mt-4">
                            <div class="stat-mini p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="text-2xl font-bold" style="color: var(--primary);">{{ $tenantEligibleForArchive }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">Ready for Archive</div>
                            </div>
                            <div class="stat-mini p-3 rounded-lg text-center" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="text-2xl font-bold" style="color: var(--warning);">{{ $tenantUnpaidPreviousYears }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">Unpaid Previous Years</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t" style="border-color: var(--border-color);">
                <h4 class="text-sm font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-bolt mr-2"></i>Quick Actions
                </h4>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('invoices.index') }}" class="quick-action-btn px-4 py-2 rounded-lg text-sm"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeInvoiceModal()">
                        <i class="fas fa-list mr-2"></i>All Invoices
                    </a>
                    <a href="{{ route('invoices.create') }}" class="quick-action-btn px-4 py-2 rounded-lg text-sm"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeInvoiceModal()">
                        <i class="fas fa-plus mr-2"></i>Create Invoice
                    </a>
                    <a href="{{ route('admin.payments.index') }}" class="quick-action-btn px-4 py-2 rounded-lg text-sm"
                       style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);"
                       onclick="closeInvoiceModal()">
                        <i class="fas fa-credit-card mr-2"></i>View Payments
                    </a>
                </div>
            </div>
        </div>

        <div class="modal-footer p-6 border-t flex justify-end flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--bg-secondary);">
            <button id="cancelInvoiceModal" class="px-4 py-2 text-sm font-medium rounded-lg"
                    style="color: var(--text-secondary);">
                Close
            </button>
        </div>
    </div>
</div>
@endif

<!-- ============ ADMINISTRATIVE TOOLS MODAL ============ -->
@if($isAuthorized)
<div id="administrativeToolsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden overflow-y-auto" style="padding-top: 2rem; padding-bottom: 2rem;">
    <div class="admin-tools-modal relative mx-auto my-auto"
         style="background-color: var(--card-bg);
                border: 1px solid var(--border-color);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: modalSlideUp 0.3s ease-out;
                width: 90%;
                max-width: 1200px;
                max-height: calc(100vh - 4rem);
                display: flex;
                flex-direction: column;
                border-radius: 16px;
                overflow: hidden;">

        <div class="modal-header flex justify-between items-center p-6 border-b flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--card-bg);">
            <h3 class="text-xl font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-tools mr-3" style="color: var(--primary);"></i>
                Administrative Tools
            </h3>
            <button id="closeAdminToolsModal" class="p-2 rounded-full transition-colors duration-200"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <div class="modal-body p-6 overflow-y-auto" style="max-height: calc(100vh - 12rem); scroll-behavior: smooth;">
            <p class="text-sm mb-6" style="color: var(--text-secondary);">
                Select an administrative module to manage system functions.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <a href="{{ route('admin.security-posts.index') }}" class="admin-tools-card group"
                   onclick="closeAdminToolsModal()">
                    <div class="admin-tools-icon-container"><i class="fas fa-map-marker-alt" style="color: var(--primary);"></i></div>
                    <div class="admin-tools-content">
                        <h4 class="admin-tools-title">Security Posts</h4>
                        <p class="admin-tools-description">Manage guard stations and checkpoints</p>
                    </div>
                    <div class="admin-tools-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>

                <a href="{{ route('admin.security-shifts.index') }}" class="admin-tools-card group"
                   onclick="closeAdminToolsModal()">
                    <div class="admin-tools-icon-container"><i class="fas fa-clock" style="color: var(--success);"></i></div>
                    <div class="admin-tools-content">
                        <h4 class="admin-tools-title">Security Shifts</h4>
                        <p class="admin-tools-description">Define and manage shift patterns</p>
                    </div>
                    <div class="admin-tools-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>

                <a href="{{ route('admin.security-schedules.index') }}" class="admin-tools-card group"
                   onclick="closeAdminToolsModal()">
                    <div class="admin-tools-icon-container"><i class="fas fa-calendar-alt" style="color: var(--info);"></i></div>
                    <div class="admin-tools-content">
                        <h4 class="admin-tools-title">Security Schedules</h4>
                        <p class="admin-tools-description">Schedule personnel to posts</p>
                    </div>
                    <div class="admin-tools-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>

                <a href="{{ route('admin.supervisor-assignments.index') }}" class="admin-tools-card group"
                   onclick="closeAdminToolsModal()">
                    <div class="admin-tools-icon-container"><i class="fas fa-user-shield" style="color: var(--warning);"></i></div>
                    <div class="admin-tools-content">
                        <h4 class="admin-tools-title">Supervisor Assignments</h4>
                        <p class="admin-tools-description">Assign supervisors to posts</p>
                    </div>
                    <div class="admin-tools-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>

                <a href="{{ route('admin.ownership-transfers.index') }}" class="admin-tools-card group"
                   onclick="closeAdminToolsModal()">
                    <div class="admin-tools-icon-container"><i class="fas fa-exchange-alt" style="color: var(--danger);"></i></div>
                    <div class="admin-tools-content">
                        <h4 class="admin-tools-title">Ownership Transfers</h4>
                        <p class="admin-tools-description">Manage property transfers</p>
                    </div>
                    <div class="admin-tools-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
            </div>
        </div>

        <div class="modal-footer p-6 border-t flex justify-end flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--bg-secondary);">
            <button id="cancelAdminToolsModal" class="px-4 py-2 text-sm font-medium"
                    style="color: var(--text-secondary);">Close</button>
        </div>
    </div>
</div>
@endif

<!-- ============ THEME SETTINGS MODAL ============ -->
<div id="themeSettingsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden overflow-y-auto" style="padding-top: 2rem; padding-bottom: 2rem;">
    <div class="theme-modal-compact relative mx-auto my-auto"
         style="background-color: var(--card-bg);
                border: 1px solid var(--border-color);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: modalSlideUp 0.3s ease-out;
                width: 90%;
                max-width: 400px;
                max-height: calc(100vh - 4rem);
                display: flex;
                flex-direction: column;
                border-radius: 12px;
                overflow: hidden;">

        <div class="theme-modal-header-compact flex justify-between items-center p-4 border-b flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--card-bg);">
            <h3 class="text-base font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-palette mr-2" style="color: var(--primary);"></i>Theme Settings
            </h3>
            <button id="closeThemeModal" class="p-1 rounded-full transition-colors duration-200"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="theme-modal-body-compact p-4 overflow-y-auto" style="max-height: calc(100vh - 10rem); scroll-behavior: smooth;">
            <div class="mb-6">
                <h4 class="text-sm font-medium mb-2" style="color: var(--text-primary);">Appearance</h4>
                <div class="grid grid-cols-3 gap-3">
                    <div class="appearance-option-compact cursor-pointer text-center" data-theme="light">
                        <div class="bg-white border rounded-lg p-3 mb-1"><i class="fas fa-sun text-yellow-500"></i></div>
                        <span class="text-xs">Light</span>
                    </div>
                    <div class="appearance-option-compact cursor-pointer text-center" data-theme="dark">
                        <div class="bg-gray-800 border rounded-lg p-3 mb-1"><i class="fas fa-moon text-blue-300"></i></div>
                        <span class="text-xs">Dark</span>
                    </div>
                    <div class="appearance-option-compact cursor-pointer text-center" data-theme="system">
                        <div class="bg-gradient-to-r from-gray-100 to-gray-800 border rounded-lg p-3 mb-1"><i class="fas fa-desktop"></i></div>
                        <span class="text-xs">System</span>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <h4 class="text-sm font-medium mb-2" style="color: var(--text-primary);">Sidebar Theme</h4>
                <div class="grid grid-cols-5 gap-2">
                    <div class="theme-option-compact cursor-pointer text-center" data-theme="default">
                        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg h-12 mb-1"></div>
                        <span class="text-xs">Default</span>
                    </div>
                    <div class="theme-option-compact cursor-pointer text-center" data-theme="dark">
                        <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-lg h-12 mb-1"></div>
                        <span class="text-xs">Dark</span>
                    </div>
                    <div class="theme-option-compact cursor-pointer text-center" data-theme="light">
                        <div class="bg-white border rounded-lg h-12 mb-1"></div>
                        <span class="text-xs">Light</span>
                    </div>
                    <div class="theme-option-compact cursor-pointer text-center" data-theme="blue">
                        <div class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-lg h-12 mb-1"></div>
                        <span class="text-xs">Blue</span>
                    </div>
                    <div class="theme-option-compact cursor-pointer text-center" data-theme="green">
                        <div class="bg-gradient-to-br from-green-600 to-green-800 rounded-lg h-12 mb-1"></div>
                        <span class="text-xs">Green</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="theme-modal-footer-compact flex justify-end p-4 border-t flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--bg-secondary);">
            <button id="closeThemeModalBtn" class="px-4 py-2 text-sm font-medium rounded-lg"
                    style="color: var(--text-secondary);">Close</button>
        </div>
    </div>
</div>

<!-- Main Content Header -->
<div class="content" id="mainContent">
    <header class="header flex items-center justify-between">
        <div class="flex items-center">
            <button id="toggleSidebarMobile" class="mobile-menu-btn mr-4" style="color: var(--text-secondary);">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <h2 class="text-xl font-semibold" style="color: var(--text-primary);">@yield('title', 'Dashboard')</h2>
        </div>

        <div class="flex items-center space-x-4">
            @if($isAuthorized)
            {{-- ============================================ --}}
            {{-- ✅ Quick search — Alpine live dropdown (FAST) --}}
            {{-- ============================================ --}}
            <div class="relative" x-data="quickSearch()" @click.away="close()">
                <div class="flex items-center space-x-2">
                    <div class="relative hidden md:block">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2"
                           style="color: var(--text-secondary);"></i>

                        <input type="text"
                               id="quickSearchInput"
                               x-model="query"
                               @input.debounce.180ms="suggest()"
                               @keydown="onKeydown($event)"
                               @focus="query.length >= 2 ? (open = true, suggest()) : null"
                               placeholder="Quick search... (Press /)"
                               class="header-search pl-10 pr-10 transition-colors duration-200"
                               style="color: var(--text-primary); background-color: var(--bg-secondary); border: 1px solid var(--border-color); outline: none;"
                               data-role="{{ $searchRole }}"
                               autocomplete="off"
                               aria-autocomplete="list"
                               aria-expanded="false">

                        <button id="advancedSearchBtn"
                                type="button"
                                class="absolute right-2 top-1/2 transform -translate-y-1/2 p-1 rounded"
                                title="Advanced search"
                                style="color: var(--text-secondary);">
                            <i class="fas fa-sliders-h text-sm"></i>
                        </button>

                        {{-- ✅ NEW: spinner shown while fetching (uses .search-spinner from layout) --}}
                        <div x-show="loading" x-cloak
                             class="absolute right-8 top-1/2 transform -translate-y-1/2">
                            <span class="search-spinner"></span>
                        </div>

                        {{-- Live dropdown results --}}
                        <div x-show="open" x-cloak
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 transform -translate-y-1"
                             x-transition:enter-end="opacity-100 transform translate-y-0"
                             class="absolute top-full mt-2 w-96 max-h-96 overflow-y-auto rounded-lg shadow-lg z-50"
                             style="background-color: var(--card-bg); border: 1px solid var(--border-color);">

                            <template x-if="loading && isEmpty()">
                                <div class="p-4 text-center text-sm" style="color: var(--text-secondary);">
                                    <i class="fas fa-spinner fa-spin mr-2"></i>Searching…
                                </div>
                            </template>

                            <template x-if="!loading && isEmpty()">
                                <div class="p-4 text-center text-sm" style="color: var(--text-secondary);">
                                    No results for "<span x-text="query" class="font-medium"></span>"
                                </div>
                            </template>

                            <template x-for="(items, category) in results" :key="category">
                                <div>
                                    <div class="px-3 py-2 text-xs font-semibold uppercase tracking-wide flex items-center justify-between"
                                         style="color: var(--text-secondary); background-color: var(--bg-secondary);">
                                        <span x-text="category.replace(/_/g, ' ')"></span>
                                        <span class="search-category-pill" x-text="items.length"></span>
                                    </div>
                                    <template x-for="item in items" :key="item.id">
                                        <a :href="item.url"
                                           :data-search-active="flatItems[activeIndex] && flatItems[activeIndex].id === item.id ? 'true' : 'false'"
                                           class="flex items-center justify-between px-3 py-2 transition-colors duration-150"
                                           :style="flatItems[activeIndex] && flatItems[activeIndex].id === item.id
                                                    ? 'background-color: rgba(var(--primary-rgb),0.12); color: var(--text-primary);'
                                                    : 'color: var(--text-primary);'">
                                            <div class="min-w-0 flex-1">
                                                <div class="text-sm font-medium truncate" x-text="item.label"></div>
                                                <div class="text-xs truncate" style="color: var(--text-secondary);" x-text="item.sub"></div>
                                            </div>
                                            <i class="fas fa-arrow-right text-xs ml-2" style="color: var(--text-secondary);"></i>
                                        </a>
                                    </template>
                                </div>
                            </template>

                            <template x-if="!loading && !isEmpty()">
                                <a @click.prevent="goToFullResults()"
                                   href="#"
                                   class="block px-3 py-2 text-center text-sm border-t cursor-pointer"
                                   style="color: var(--primary); border-color: var(--border-color);">
                                    <i class="fas fa-list mr-1"></i>
                                    View all results for "<span x-text="query"></span>"
                                </a>
                            </template>
                        </div>
                    </div>

                    <button id="mobileSearchBtn" type="button" class="md:hidden p-2 rounded-full"
                            style="color: var(--text-secondary);">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            @endif

            <div class="header-buttons flex items-center space-x-4">
                <div class="relative" x-data="{ open: false }" @click.away="open = false">
                    <button @click="open = !open; if(open) window.dispatchEvent(new CustomEvent('notifications-reload'))" class="relative p-2" id="notificationBell"
                            style="color: var(--text-secondary);">
                        <div class="relative">
                            <i class="fas fa-bell text-xl"></i>
                            @if($sidebarUnreadCount > 0)
                                <span class="absolute -top-2 -right-2 rounded-full w-6 h-6 text-xs flex items-center justify-center"
                                      style="background-color: var(--danger); color: white;">
                                    {{ $sidebarUnreadCount > 9 ? '9+' : $sidebarUnreadCount }}
                                </span>
                            @endif
                        </div>
                    </button>

                    <div x-show="open" x-transition class="absolute right-0 mt-2 w-80 rounded-lg shadow-lg z-50 notification-dropdown"
                         style="display: none; background-color: var(--card-bg); border: 1px solid var(--border-color);">
                        <div class="p-4 border-b" style="border-color: var(--border-color);">
                            <div class="flex justify-between items-center">
                                <h3 class="font-semibold" style="color: var(--text-primary);">
                                    <i class="fas fa-bell mr-2"></i>Notifications
                                </h3>
                                @if($sidebarUnreadCount > 0)
                                    <button onclick="markAllAsRead()" class="text-sm" style="color: var(--primary);">Mark all as read</button>
                                @endif
                            </div>
                        </div>
                        <div class="max-h-96 overflow-y-auto" id="notificationList">
                            <div class="p-4 text-center" style="color: var(--text-secondary);">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Loading notifications...
                            </div>
                        </div>
                        <div class="p-3 border-t" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                            <a href="{{ route('notifications.index') }}" class="text-sm" style="color: var(--primary);">
                                <i class="fas fa-list mr-1"></i>View all
                            </a>
                        </div>
                    </div>
                </div>

                <button class="relative p-2 rounded-full" style="color: var(--text-secondary);">
                    <i class="far fa-envelope text-xl"></i>
                </button>
            </div>

            <div class="dropdown relative">
                <button id="userMenuButton" class="flex items-center space-x-2">
                    <div class="avatar-minimal">
                        @if(Auth::user()->photo)
                            <img src="{{ Storage::disk('public')->url('users/photos/' . Auth::user()->photo) }}"
                                 alt="{{ Auth::user()->name }}"
                                 class="w-8 h-8 rounded-full object-cover"
                                 onerror="this.onerror=null;this.src='{{ asset('images/default-avatar.png') }}';">
                        @else
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-sm font-semibold">
                                {{ substr(Auth::user()->name, 0, 2) }}
                            </div>
                        @endif
                    </div>
                    <i class="fas fa-chevron-down text-xs" style="color: var(--text-secondary);"></i>
                </button>

                <div id="userDropdown" class="dropdown-menu" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                    @if($isDeveloper)
                        <a href="{{ route('developer.profile.edit') }}" class="dropdown-item">
                            <i class="far fa-user mr-3"></i><span>My Profile</span>
                        </a>
                    @else
                        <a href="{{ route('admin.profile.edit') }}" class="dropdown-item">
                            <i class="far fa-user mr-3"></i><span>My Profile</span>
                        </a>
                    @endif

                    <div class="border-t my-1" style="border-color: var(--border-color);"></div>

                    @if($isSuperAdmin)
                        <a href="{{ route('admin.system-settings.index') }}" class="dropdown-item">
                            <i class="fas fa-cog mr-3"></i><span>System Settings</span>
                        </a>
                    @endif

                    <a href="{{ route('notifications.index') }}" class="dropdown-item">
                        <i class="far fa-bell mr-3"></i><span>Notifications</span>
                        @if($sidebarUnreadCount > 0)
                            <span class="ml-auto text-xs px-2 py-1 rounded-full" style="background-color: var(--danger); color: white;">
                                {{ $sidebarUnreadCount > 9 ? '9+' : $sidebarUnreadCount }}
                            </span>
                        @endif
                    </a>

                    @if($isSuperAdmin || $isDeveloper)
                        <a href="{{ route('admin.payment-providers.index') }}" class="dropdown-item">
                            <i class="fas fa-credit-card mr-3"></i><span>Payment Providers</span>
                        </a>
                    @endif

                    <div class="border-t my-1" style="border-color: var(--border-color);"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item w-full text-left" style="color: var(--danger);">
                            <i class="fas fa-sign-out-alt mr-3"></i><span>Logout</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <button id="scrollToTop" class="fixed bottom-6 right-6 w-12 h-12 rounded-full shadow-lg flex items-center justify-center z-50 transition-opacity duration-300 opacity-0 pointer-events-none"
            style="background-color: var(--primary); color: white;">
        <i class="fas fa-arrow-up"></i>
    </button>

@include('layouts.partials.admin.scripts.styles')
@include('layouts.partials.admin.scripts.scripts')