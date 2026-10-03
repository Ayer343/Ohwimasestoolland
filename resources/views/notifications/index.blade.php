{{-- notifications/index.blade.php --}}
@php
    // Get authenticated user
    $user = auth()->user();
    
    // ============ DYNAMIC LAYOUT BASED ON SELECTED ROLE ============
    $currentSelectedRole = session('selected_role');
    
    // Determine layout based on session role (dashboard switcher) OR user's roles
    if ($currentSelectedRole) {
        switch ($currentSelectedRole) {
            case 'landlord':
                $layout = 'layouts.landlord';
                break;
            case 'field-agent':
                $layout = 'layouts.field';
                break;
            case 'tenant':
                $layout = 'layouts.tenant';
                break;
            case 'security-personnel':
                $layout = 'layouts.secu';
                break;
            case 'super-admin':
            case 'admin':
                $layout = 'layouts.app';
                break;
            case 'developer':
                $layout = 'layouts.dev';
                break;
            case 'sanitation-personnel':
                $layout = 'layouts.san';
                break;
            case 'contractor':
                $layout = 'layouts.contract';
                break;
            default:
                $layout = 'layouts.app';
        }
    } else {
        // Fallback to role-based layout detection
        if ($user->isLandlord()) {
            $layout = 'layouts.landlord';
        } elseif ($user->isAdmin() || $user->isSuperAdmin()) {
            $layout = 'layouts.app';
        } elseif ($user->isFieldAgent()) {
            $layout = 'layouts.field';
        } elseif ($user->isSecurityPersonnel()) {
            $layout = 'layouts.secu';
        } elseif ($user->isDeveloper()) {
            $layout = 'layouts.dev';
        } elseif ($user->isTenant()) {
            $layout = 'layouts.tenant';
        } elseif ($user->isSanitationPersonnel()) {
            $layout = 'layouts.san';
        } elseif ($user->isContractor()) {
            $layout = 'layouts.contract';
        } else {
            $layout = 'layouts.app';
        }
    }
    
    // Get user type for dynamic routing and display
    $isLandlord = $user->isLandlord();
    $isAdmin = $user->isAdmin();
    $isSuperAdmin = $user->isSuperAdmin();
    $isDeveloper = $user->isDeveloper();
    $isFieldAgent = $user->isFieldAgent();
    $isSecurityPersonnel = $user->isSecurityPersonnel();
    $isTenant = $user->isTenant();
    $isSanitationPersonnel = $user->isSanitationPersonnel();
    $isContractor = $user->isContractor();
    
    // Create dynamic title and badge based on current selected role
    $pageTitle = 'Notifications';
    $roleBadge = '';
    $roleIcon = 'fa-bell';
    $roleColor = 'var(--primary)';
    
    if ($currentSelectedRole) {
        switch ($currentSelectedRole) {
            case 'landlord':
                $pageTitle .= ' - Landlord Portal';
                $roleBadge = '<i class="fas fa-home mr-1"></i> Landlord View';
                $roleIcon = 'fa-home';
                $roleColor = 'var(--success)';
                break;
            case 'field-agent':
                $pageTitle .= ' - Field Agent Portal';
                $roleBadge = '<i class="fas fa-user-check mr-1"></i> Field Agent View';
                $roleIcon = 'fa-user-check';
                $roleColor = 'var(--info)';
                break;
            case 'tenant':
                $pageTitle .= ' - Tenant Portal';
                $roleBadge = '<i class="fas fa-user-friends mr-1"></i> Tenant View';
                $roleIcon = 'fa-user-friends';
                $roleColor = 'var(--warning)';
                break;
            case 'security-personnel':
                $pageTitle .= ' - Security Portal';
                $roleBadge = '<i class="fas fa-shield-alt mr-1"></i> Security View';
                $roleIcon = 'fa-shield-alt';
                $roleColor = 'var(--warning)';
                break;
            case 'super-admin':
                $pageTitle .= ' - Super Admin Portal';
                $roleBadge = '<i class="fas fa-crown mr-1"></i> Super Admin View';
                $roleIcon = 'fa-crown';
                $roleColor = 'var(--primary)';
                break;
            case 'admin':
                $pageTitle .= ' - Admin Portal';
                $roleBadge = '<i class="fas fa-user-shield mr-1"></i> Admin View';
                $roleIcon = 'fa-user-shield';
                $roleColor = 'var(--success)';
                break;
            case 'developer':
                $pageTitle .= ' - Developer Portal';
                $roleBadge = '<i class="fas fa-code mr-1"></i> Developer View';
                $roleIcon = 'fa-code';
                $roleColor = 'var(--secondary)';
                break;
            case 'sanitation-personnel':
                $pageTitle .= ' - Sanitation Portal';
                $roleBadge = '<i class="fas fa-trash-alt mr-1"></i> Sanitation View';
                $roleIcon = 'fa-trash-alt';
                $roleColor = '#10b981'; // Emerald green for sanitation
                break;
            case 'contractor':
                $pageTitle .= ' - Contractor Portal';
                $roleBadge = '<i class="fas fa-hard-hat mr-1"></i> Contractor View';
                $roleIcon = 'fa-hard-hat';
                $roleColor = '#f59e0b'; // Amber for contractor
                break;
        }
    } else {
        // Fallback to role-based badge
        if ($isLandlord) {
            $roleBadge = '<i class="fas fa-home mr-1"></i> Landlord View';
            $roleIcon = 'fa-home';
            $roleColor = 'var(--success)';
        } elseif ($isAdmin) {
            $roleBadge = '<i class="fas fa-user-shield mr-1"></i> Admin View';
            $roleIcon = 'fa-user-shield';
            $roleColor = 'var(--success)';
        } elseif ($isSuperAdmin) {
            $roleBadge = '<i class="fas fa-crown mr-1"></i> Super Admin View';
            $roleIcon = 'fa-crown';
            $roleColor = 'var(--primary)';
        } elseif ($isFieldAgent) {
            $roleBadge = '<i class="fas fa-user-check mr-1"></i> Field Agent View';
            $roleIcon = 'fa-user-check';
            $roleColor = 'var(--info)';
        } elseif ($isDeveloper) {
            $roleBadge = '<i class="fas fa-code mr-1"></i> Developer View';
            $roleIcon = 'fa-code';
            $roleColor = 'var(--secondary)';
        } elseif ($isSecurityPersonnel) {
            $roleBadge = '<i class="fas fa-shield-alt mr-1"></i> Security View';
            $roleIcon = 'fa-shield-alt';
            $roleColor = 'var(--warning)';
        } elseif ($isTenant) {
            $roleBadge = '<i class="fas fa-user-friends mr-1"></i> Tenant View';
            $roleIcon = 'fa-user-friends';
            $roleColor = 'var(--warning)';
        } elseif ($isSanitationPersonnel) {
            $roleBadge = '<i class="fas fa-trash-alt mr-1"></i> Sanitation View';
            $roleIcon = 'fa-trash-alt';
            $roleColor = '#10b981';
        } elseif ($isContractor) {
            $roleBadge = '<i class="fas fa-hard-hat mr-1"></i> Contractor View';
            $roleIcon = 'fa-hard-hat';
            $roleColor = '#f59e0b';
        }
    }
    
    // Determine route prefix based on current selected role
    if ($currentSelectedRole) {
        switch ($currentSelectedRole) {
            case 'landlord':
                $routePrefix = 'landlord.notifications';
                break;
            case 'field-agent':
                $routePrefix = 'field-agent.notifications';
                break;
            case 'tenant':
                $routePrefix = 'tenant.notifications';
                break;
            case 'security-personnel':
                $routePrefix = 'security.notifications';
                break;
            case 'super-admin':
            case 'admin':
                $routePrefix = 'admin.notifications';
                break;
            case 'developer':
                $routePrefix = 'developer.notifications';
                break;
            case 'sanitation-personnel':
                $routePrefix = 'sanitation.notifications';
                break;
            case 'contractor':
                $routePrefix = 'contractor.notifications';
                break;
            default:
                $routePrefix = 'notifications';
        }
    } else {
        // Fallback to role-based route prefix
        if ($isLandlord) {
            $routePrefix = 'landlord.notifications';
        } elseif ($isAdmin || $isSuperAdmin) {
            $routePrefix = 'admin.notifications';
        } elseif ($isDeveloper) {
            $routePrefix = 'developer.notifications';
        } elseif ($isFieldAgent) {
            $routePrefix = 'field-agent.notifications';
        } elseif ($isSecurityPersonnel) {
            $routePrefix = 'security.notifications';
        } elseif ($isTenant) {
            $routePrefix = 'tenant.notifications';
        } elseif ($isSanitationPersonnel) {
            $routePrefix = 'sanitation.notifications';
        } elseif ($isContractor) {
            $routePrefix = 'contractor.notifications';
        } else {
            $routePrefix = 'notifications';
        }
    }
    
    // Get role-specific statistics from controller
    $totalCount = $stats['total'] ?? 0;
    $unreadCount = $stats['unread'] ?? 0;
    $readCount = $stats['read'] ?? 0;
    $todayCount = $stats['today'] ?? 0;
    
    $currentFilter = request()->get('filter');
    $currentCategory = request()->get('category');
    $currentPriority = request()->get('priority');
    $searchQuery = request()->get('search');
    $dateFrom = request()->get('from_date');
    $dateTo = request()->get('to_date');
    
    // Build role parameter for API calls
    $roleParam = $currentSelectedRole ? "?role=" . urlencode($currentSelectedRole) : "";
    $roleParamAmp = $currentSelectedRole ? "&role=" . urlencode($currentSelectedRole) : "";
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Role Context Banner - Shows when user has multiple roles -->
    @if($currentSelectedRole && ($user->roles->count() > 1 || $user->isAdmin() || $user->isSuperAdmin()))
    <div class="card bg-gradient-to-r from-primary/10 to-secondary/10 border-l-4 border-primary">
        <div class="p-4">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-primary mr-3 text-xl"></i>
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-primary);">
                            <strong>Viewing as: {{ ucfirst(str_replace('-', ' ', $currentSelectedRole)) }}</strong>
                        </p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            You're seeing notifications relevant to your {{ ucfirst(str_replace('-', ' ', $currentSelectedRole)) }} dashboard only.
                            @if($user->roles->count() > 1)
                            <span class="inline-flex items-center ml-2">
                                <i class="fas fa-exchange-alt mr-1 text-xs"></i>
                                <a href="{{ route('dashboard.switch') }}" class="hover:underline" style="color: var(--primary);">
                                    Switch role to see other notifications
                                </a>
                            </span>
                            @endif
                        </p>
                    </div>
                </div>
                @if($unreadCount > 0)
                <div class="text-sm">
                    <span class="px-2 py-1 rounded-full bg-warning/20 text-warning">
                        <i class="fas fa-bell mr-1"></i> {{ $unreadCount }} unread for this role
                    </span>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Header Card with Role-Aware Display -->
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-4">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, {{ $roleColor }} 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: {{ $roleColor }};">
                        <i class="fas {{ $roleIcon }} text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center flex-wrap gap-2" style="color: var(--text-primary);">
                        <i class="fas fa-bell mr-2" style="color: {{ $roleColor }};"></i> 
                        Notifications
                        @if($unreadCount > 0)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                            <i class="fas fa-envelope mr-1"></i> {{ $unreadCount }} Unread
                        </span>
                        @endif
                    </h2>
                    <div class="text-sm flex items-center flex-wrap gap-2 mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Manage your notifications and stay updated with system activities</span>
                        <span class="mx-1">•</span>
                        <span class="px-2 py-0.5 text-xs rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1); color: {{ $roleColor }}; border: 1px solid rgba(var(--primary-rgb), 0.3);">
                            {!! $roleBadge !!}
                        </span>
                        @if($currentSelectedRole)
                        <span class="text-xs" style="color: var(--text-secondary);">
                            <i class="fas fa-exchange-alt mr-1"></i> Dashboard switched
                        </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                </div>
                
                @if($unreadCount > 0)
                <button type="button" 
                        id="markAllReadBtn"
                        class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                        style="background: linear-gradient(135deg, var(--success) 0%, #20b86d 100%); color: white; border: none; cursor: pointer;">
                    <i class="fas fa-check-double mr-1"></i> Mark All Read
                </button>
                @endif
                
                @if($totalCount > 0)
                <div class="relative" x-data="{ open: false }">
                    <button type="button" 
                            onclick="toggleBulkActionMode()"
                            id="bulkActionBtn"
                            class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                            style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3); cursor: pointer;">
                        <i class="fas fa-check-double mr-1"></i> Bulk Actions
                    </button>
                </div>
                
                <button type="button" 
                        id="clearAllBtn"
                        class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3); cursor: pointer;">
                    <i class="fas fa-trash-alt mr-1"></i> Clear All
                </button>
                
                <button type="button" 
                        id="exportBtn"
                        class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                        style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3); cursor: pointer;">
                    <i class="fas fa-download mr-1"></i> Export
                </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Bulk Action Bar (Hidden by default) -->
    <div id="bulkActionBar" class="card hidden">
        <div class="p-4 flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center">
                <span class="text-sm font-medium" style="color: var(--text-primary);">
                    <i class="fas fa-check-square mr-2"></i>
                    <span id="selectedCount">0</span> notification(s) selected
                </span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" 
                        id="bulkMarkReadBtn"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium inline-flex items-center" 
                        style="background: linear-gradient(135deg, var(--success) 0%, #20b86d 100%); color: white; border: none; cursor: pointer;">
                    <i class="fas fa-check-double mr-1"></i> Mark as Read
                </button>
                <button type="button" 
                        id="bulkDeleteBtn"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium inline-flex items-center" 
                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3); cursor: pointer;">
                    <i class="fas fa-trash-alt mr-1"></i> Delete Selected
                </button>
                <button type="button" 
                        id="cancelBulkBtn"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium inline-flex items-center" 
                        style="background-color: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border-color); cursor: pointer;">
                    <i class="fas fa-times mr-1"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Total Notifications</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);" id="totalCount">{{ number_format($totalCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-bell text-lg" style="color: var(--primary);"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Unread</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);" id="unreadCount">{{ number_format($unreadCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-envelope text-lg" style="color: var(--warning);"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Read</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);" id="readCount">{{ number_format($readCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1);">
                        <i class="fas fa-envelope-open text-lg" style="color: var(--success);"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Today</p>
                        <p class="text-2xl font-bold" style="color: var(--text-primary);" id="todayCount">{{ number_format($todayCount) }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1);">
                        <i class="fas fa-calendar-day text-lg" style="color: var(--info);"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Table Container -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Filters Sidebar -->
        <div class="lg:col-span-1">
            <div class="card mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-filter mr-2" style="color: {{ $roleColor }};"></i> Filters
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-search mr-1"></i> Search
                            </label>
                            <form method="GET" action="{{ route($routePrefix . '.index') }}" id="searchForm">
                                <div class="relative">
                                    <input type="text" 
                                           name="search" 
                                           id="searchInput"
                                           value="{{ $searchQuery }}" 
                                           placeholder="Search notifications..." 
                                           class="w-full px-3 py-2 rounded-lg text-sm"
                                           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                                    <button type="submit" class="absolute right-2 top-1/2 transform -translate-y-1/2">
                                        <i class="fas fa-search" style="color: var(--text-secondary);"></i>
                                    </button>
                                </div>
                                @if($searchQuery)
                                <a href="{{ route($routePrefix . '.index', $currentSelectedRole ? ['role' => $currentSelectedRole] : []) }}" class="text-xs mt-1 inline-block hover:underline" style="color: {{ $roleColor }};">
                                    <i class="fas fa-times mr-1"></i> Clear search
                                </a>
                                @endif
                            </form>
                        </div>
                        
                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <label class="block text-sm font-medium mb-3" style="color: var(--text-primary);">
                                <i class="fas fa-tag mr-1"></i> Status
                            </label>
                            <div class="space-y-2">
                                <a href="{{ route($routePrefix . '.index', array_merge(['search' => $searchQuery, 'category' => $currentCategory, 'priority' => $currentPriority, 'from_date' => $dateFrom, 'to_date' => $dateTo], $currentSelectedRole ? ['role' => $currentSelectedRole] : [])) }}" 
                                   class="flex items-center justify-between px-3 py-2 rounded-lg transition-all {{ !$currentFilter && !$currentCategory && !$currentPriority ? 'active-filter' : '' }}"
                                   style="{{ !$currentFilter && !$currentCategory && !$currentPriority ? 'background: linear-gradient(135deg, ' . $roleColor . ', var(--secondary)); color: white;' : 'background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);' }}">
                                    <span><i class="fas fa-list mr-2"></i> All Notifications</span>
                                    <span class="px-2 py-0.5 rounded-full text-xs" style="{{ !$currentFilter && !$currentCategory && !$currentPriority ? 'background-color: rgba(255,255,255,0.2)' : 'background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);' }}">{{ $totalCount }}</span>
                                </a>
                                <a href="{{ route($routePrefix . '.index', array_merge(['filter' => 'unread'], array_filter(['search' => $searchQuery, 'category' => $currentCategory, 'priority' => $currentPriority, 'from_date' => $dateFrom, 'to_date' => $dateTo]), $currentSelectedRole ? ['role' => $currentSelectedRole] : [])) }}" 
                                   class="flex items-center justify-between px-3 py-2 rounded-lg transition-all {{ $currentFilter === 'unread' ? 'active-filter' : '' }}"
                                   style="{{ $currentFilter === 'unread' ? 'background: linear-gradient(135deg, var(--warning), #ff8c00); color: white;' : 'background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);' }}">
                                    <span><i class="fas fa-envelope mr-2"></i> Unread</span>
                                    <span class="px-2 py-0.5 rounded-full text-xs" style="{{ $currentFilter === 'unread' ? 'background-color: rgba(255,255,255,0.2)' : 'background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);' }}">{{ $unreadCount }}</span>
                                </a>
                                <a href="{{ route($routePrefix . '.index', array_merge(['filter' => 'read'], array_filter(['search' => $searchQuery, 'category' => $currentCategory, 'priority' => $currentPriority, 'from_date' => $dateFrom, 'to_date' => $dateTo]), $currentSelectedRole ? ['role' => $currentSelectedRole] : [])) }}" 
                                   class="flex items-center justify-between px-3 py-2 rounded-lg transition-all {{ $currentFilter === 'read' ? 'active-filter' : '' }}"
                                   style="{{ $currentFilter === 'read' ? 'background: linear-gradient(135deg, var(--success), #20b86d); color: white;' : 'background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);' }}">
                                    <span><i class="fas fa-envelope-open mr-2"></i> Read</span>
                                    <span class="px-2 py-0.5 rounded-full text-xs" style="{{ $currentFilter === 'read' ? 'background-color: rgba(255,255,255,0.2)' : 'background-color: rgba(var(--success-rgb), 0.1); color: var(--success);' }}">{{ $readCount }}</span>
                                </a>
                                <a href="{{ route($routePrefix . '.index', array_merge(['filter' => 'today'], array_filter(['search' => $searchQuery, 'category' => $currentCategory, 'priority' => $currentPriority, 'from_date' => $dateFrom, 'to_date' => $dateTo]), $currentSelectedRole ? ['role' => $currentSelectedRole] : [])) }}" 
                                   class="flex items-center justify-between px-3 py-2 rounded-lg transition-all {{ $currentFilter === 'today' ? 'active-filter' : '' }}"
                                   style="{{ $currentFilter === 'today' ? 'background: linear-gradient(135deg, var(--info), #00b5cc); color: white;' : 'background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);' }}">
                                    <span><i class="fas fa-calendar-day mr-2"></i> Today</span>
                                    <span class="px-2 py-0.5 rounded-full text-xs" style="{{ $currentFilter === 'today' ? 'background-color: rgba(255,255,255,0.2)' : 'background-color: rgba(var(--info-rgb), 0.1); color: var(--info);' }}">{{ $todayCount }}</span>
                                </a>
                            </div>
                        </div>

                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <label class="block text-sm font-medium mb-3" style="color: var(--text-primary);">
                                <i class="fas fa-folder mr-1"></i> Category
                            </label>
                            <div class="space-y-2 max-h-48 overflow-y-auto">
                                @php
                                    $categories = $stats['by_category'] ?? [];
                                @endphp
                                @forelse($categories as $catName => $catCount)
                                <a href="{{ route($routePrefix . '.index', array_merge(['category' => $catName], array_filter(['search' => $searchQuery, 'priority' => $currentPriority, 'from_date' => $dateFrom, 'to_date' => $dateTo]), $currentSelectedRole ? ['role' => $currentSelectedRole] : [])) }}" 
                                   class="flex items-center justify-between px-3 py-2 rounded-lg transition-all {{ $currentCategory === $catName ? 'active-filter' : '' }}"
                                   style="{{ $currentCategory === $catName ? 'background: linear-gradient(135deg, ' . $roleColor . ', var(--secondary)); color: white;' : 'background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);' }}">
                                    <span><i class="fas fa-tag mr-2"></i> {{ ucfirst($catName) }}</span>
                                    <span class="px-2 py-0.5 rounded-full text-xs">{{ $catCount }}</span>
                                </a>
                                @empty
                                <div class="text-sm text-center py-2" style="color: var(--text-secondary);">
                                    No categories available
                                </div>
                                @endforelse
                            </div>
                        </div>

                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <label class="block text-sm font-medium mb-3" style="color: var(--text-primary);">
                                <i class="fas fa-chart-line mr-1"></i> Priority
                            </label>
                            <div class="space-y-2">
                                @foreach([3 => 'High', 2 => 'Medium', 1 => 'Low', 0 => 'Normal'] as $priorityValue => $priorityLabel)
                                <a href="{{ route($routePrefix . '.index', array_merge(['priority' => $priorityValue], array_filter(['search' => $searchQuery, 'category' => $currentCategory, 'from_date' => $dateFrom, 'to_date' => $dateTo]), $currentSelectedRole ? ['role' => $currentSelectedRole] : [])) }}" 
                                   class="flex items-center justify-between px-3 py-2 rounded-lg transition-all {{ $currentPriority == $priorityValue ? 'active-filter' : '' }}"
                                   style="{{ $currentPriority == $priorityValue ? 'background: linear-gradient(135deg, ' . $roleColor . ', var(--secondary)); color: white;' : 'background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);' }}">
                                    <span>
                                        <i class="fas fa-flag mr-2" style="color: {{ $priorityValue >= 2 ? 'var(--danger)' : ($priorityValue == 1 ? 'var(--warning)' : 'var(--info)') }};"></i>
                                        {{ $priorityLabel }}
                                    </span>
                                </a>
                                @endforeach
                            </div>
                        </div>

                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <label class="block text-sm font-medium mb-3" style="color: var(--text-primary);">
                                <i class="fas fa-calendar mr-1"></i> Date Range
                            </label>
                            <form method="GET" action="{{ route($routePrefix . '.index') }}" id="dateRangeForm">
                                <div class="space-y-2">
                                    <input type="date" 
                                           name="from_date" 
                                           value="{{ $dateFrom }}" 
                                           placeholder="From Date"
                                           class="w-full px-3 py-2 rounded-lg text-sm"
                                           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                                    <input type="date" 
                                           name="to_date" 
                                           value="{{ $dateTo }}" 
                                           placeholder="To Date"
                                           class="w-full px-3 py-2 rounded-lg text-sm"
                                           style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary);">
                                    @if($currentSelectedRole)
                                    <input type="hidden" name="role" value="{{ $currentSelectedRole }}">
                                    @endif
                                    <button type="submit" class="w-full btn-primary px-3 py-2 rounded-lg text-sm font-medium"
                                            style="background: linear-gradient(135deg, {{ $roleColor }}, var(--secondary));">
                                        <i class="fas fa-filter mr-1"></i> Apply Date Filter
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                                <i class="fas fa-bolt mr-1"></i> Quick Actions
                            </h4>
                            <div class="space-y-2">
                                <a href="{{ route($routePrefix . '.index', $currentSelectedRole ? ['role' => $currentSelectedRole] : []) }}" class="block w-full text-center btn-secondary px-3 py-2 rounded-lg font-medium"
                                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                                    <i class="fas fa-redo mr-2"></i> Reset All Filters
                                </a>
                                <button type="button" 
                                        id="clearReadBtn"
                                        class="block w-full text-center px-3 py-2 rounded-lg font-medium"
                                        style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                                    <i class="fas fa-trash-alt mr-2"></i> Clear Read Notifications
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notifications Table -->
        <div class="lg:col-span-3">
            <div class="card p-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                            Notification List
                            @if($currentFilter || $currentCategory || $currentPriority || $searchQuery || $dateFrom)
                            <span class="text-sm font-normal ml-2">
                                @if($currentFilter)
                                <span class="px-2 py-1 rounded-full badge-primary mr-1">{{ ucfirst($currentFilter) }}</span>
                                @endif
                                @if($currentCategory)
                                <span class="px-2 py-1 rounded-full badge-primary mr-1">{{ ucfirst($currentCategory) }}</span>
                                @endif
                                @if($currentPriority)
                                <span class="px-2 py-1 rounded-full badge-primary mr-1">Priority {{ $currentPriority }}</span>
                                @endif
                                @if($searchQuery)
                                <span class="px-2 py-1 rounded-full badge-primary">Search: "{{ $searchQuery }}"</span>
                                @endif
                            </span>
                            @endif
                        </h3>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);">
                            Showing {{ $notifications->firstItem() }} to {{ $notifications->lastItem() }} of {{ $notifications->total() }} entries
                        </p>
                    </div>
                    
                    <div class="flex items-center space-x-3 mt-4 md:mt-0">
                        <div class="flex items-center space-x-2">
                            <span class="text-sm" style="color: var(--text-secondary);">Show:</span>
                            <select onchange="updatePerPage(this.value)" class="index-custom-dropdown text-sm py-1 px-2 rounded">
                                <option value="10" {{ request('per_page', 20) == 10 ? 'selected' : '' }}>10</option>
                                <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                            </select>
                        </div>
                    </div>
                </div>

                @if($notifications->isEmpty())
                    <div class="text-center py-12">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <i class="fas fa-bell-slash text-2xl" style="color: var(--primary);"></i>
                        </div>
                        <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">No notifications found</h4>
                        <p class="text-sm" style="color: var(--text-secondary);">
                            @if($currentFilter || $currentCategory || $currentPriority || $searchQuery || $dateFrom)
                                Try adjusting your filters to see more results.
                            @else
                                You're all caught up! No notifications to display.
                            @endif
                        </p>
                        @if($currentFilter || $currentCategory || $currentPriority || $searchQuery || $dateFrom)
                        <div class="mt-4">
                            <a href="{{ route($routePrefix . '.index', $currentSelectedRole ? ['role' => $currentSelectedRole] : []) }}" class="btn-primary px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                               style="background: linear-gradient(135deg, {{ $roleColor }}, var(--secondary));">
                                <i class="fas fa-sync-alt mr-2"></i> Reset Filters
                            </a>
                        </div>
                        @endif
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-full">
                            <thead>
                                <tr>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); width: 40px;">
                                        <input type="checkbox" id="selectAllCheckbox" class="rounded" style="cursor: pointer;">
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); width: 100px;">
                                        Status
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">
                                        Notification
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); width: 120px;">
                                        Category
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); width: 160px;">
                                        Time
                                    </th>
                                    <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); width: 120px;">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="notificationsTableBody">
                                @foreach($notifications as $notification)
                                @php
                                    $isUnread = is_null($notification->read_at);
                                    $canUndo = isset($notification->can_undo) ? $notification->can_undo : ($notification->created_at->diffInMinutes(now()) < 5);
                                    $icon = $notification->data['icon'] ?? 'fas fa-bell';
                                    $priority = $notification->priority ?? ($notification->data['priority'] ?? 0);
                                    $priorityColor = match($priority) {
                                        3 => 'var(--danger)',
                                        2 => 'var(--warning)',
                                        1 => 'var(--info)',
                                        default => 'var(--secondary)',
                                    };
                                    $iconColor = match($notification->data['type'] ?? 'info') {
                                        'success' => 'var(--success)',
                                        'error' => 'var(--danger)',
                                        'warning' => 'var(--warning)',
                                        'info' => 'var(--info)',
                                        default => $roleColor,
                                    };
                                    
                                    $category = $notification->category ?? ($notification->data['category'] ?? 'General');
                                    $categoryConfigs = [
                                        'System' => ['icon' => 'fa-cogs', 'bg' => 'rgba(var(--info-rgb), 0.1)', 'text' => 'var(--info)', 'border' => 'rgba(var(--info-rgb), 0.3)'],
                                        'Registration' => ['icon' => 'fa-clipboard-check', 'bg' => 'rgba(var(--primary-rgb), 0.1)', 'text' => $roleColor, 'border' => 'rgba(var(--primary-rgb), 0.3)'],
                                        'Alert' => ['icon' => 'fa-exclamation-triangle', 'bg' => 'rgba(var(--warning-rgb), 0.1)', 'text' => 'var(--warning)', 'border' => 'rgba(var(--warning-rgb), 0.3)'],
                                        'Update' => ['icon' => 'fa-sync-alt', 'bg' => 'rgba(var(--success-rgb), 0.1)', 'text' => 'var(--success)', 'border' => 'rgba(var(--success-rgb), 0.3)'],
                                        'Reminder' => ['icon' => 'fa-clock', 'bg' => 'rgba(var(--secondary-rgb), 0.1)', 'text' => 'var(--secondary)', 'border' => 'rgba(var(--secondary-rgb), 0.3)'],
                                        'Payment' => ['icon' => 'fa-credit-card', 'bg' => 'rgba(139, 92, 246, 0.1)', 'text' => '#8b5cf6', 'border' => 'rgba(139, 92, 246, 0.3)'],
                                        'Property' => ['icon' => 'fa-building', 'bg' => 'rgba(249, 115, 22, 0.1)', 'text' => '#f97316', 'border' => 'rgba(249, 115, 22, 0.3)'],
                                        'Tenant' => ['icon' => 'fa-users', 'bg' => 'rgba(6, 182, 212, 0.1)', 'text' => '#06b6d4', 'border' => 'rgba(6, 182, 212, 0.3)'],
                                        'test' => ['icon' => 'fa-flask', 'bg' => 'rgba(168, 85, 247, 0.1)', 'text' => '#a855f7', 'border' => 'rgba(168, 85, 247, 0.3)'],
                                        'Sanitation' => ['icon' => 'fa-trash-alt', 'bg' => 'rgba(16, 185, 129, 0.1)', 'text' => '#10b981', 'border' => 'rgba(16, 185, 129, 0.3)'],
                                        'Waste' => ['icon' => 'fa-recycle', 'bg' => 'rgba(16, 185, 129, 0.1)', 'text' => '#10b981', 'border' => 'rgba(16, 185, 129, 0.3)'],
                                        'Construction' => ['icon' => 'fa-hard-hat', 'bg' => 'rgba(245, 158, 11, 0.1)', 'text' => '#f59e0b', 'border' => 'rgba(245, 158, 11, 0.3)'],
                                        'Contract' => ['icon' => 'fa-file-contract', 'bg' => 'rgba(245, 158, 11, 0.1)', 'text' => '#f59e0b', 'border' => 'rgba(245, 158, 11, 0.3)'],
                                    ];
                                    $categoryConfig = $categoryConfigs[$category] ?? $categoryConfigs['System'];
                                @endphp
                                <tr data-notification-id="{{ $notification->id }}" 
                                    data-unread="{{ $isUnread ? 'true' : 'false' }}"
                                    class="notification-row transition-all duration-200"
                                    style="border-bottom: 1px solid var(--border-color); {{ $isUnread ? 'background-color: rgba(var(--primary-rgb), 0.03);' : '' }}">
                                    
                                    <td class="p-3 align-top text-center">
                                        <input type="checkbox" 
                                               class="notification-checkbox rounded" 
                                               data-id="{{ $notification->id }}"
                                               style="cursor: pointer;">
                                    </td>
                                    
                                    <td class="p-3 align-top">
                                        <div class="flex flex-col items-center gap-2">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center" 
                                                 style="background-color: rgba(var(--primary-rgb), 0.1); color: {{ $iconColor }};">
                                                <i class="{{ $icon }} text-lg"></i>
                                            </div>
                                            @if($priority > 0)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs"
                                                  style="background-color: rgba(var(--danger-rgb), 0.1); color: {{ $priorityColor }}; border: 1px solid rgba(var(--danger-rgb), 0.3);">
                                                <i class="fas fa-flag mr-1 text-xs"></i>
                                                Priority {{ $priority }}
                                            </span>
                                            @endif
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                                  style="background-color: {{ $isUnread ? 'rgba(var(--primary-rgb), 0.1)' : 'rgba(var(--success-rgb), 0.1)' }}; 
                                                         color: {{ $isUnread ? $roleColor : 'var(--success)' }};
                                                         border: 1px solid {{ $isUnread ? 'rgba(var(--primary-rgb), 0.3)' : 'rgba(var(--success-rgb), 0.3)' }};">
                                                <i class="fas {{ $isUnread ? 'fa-envelope' : 'fa-envelope-open' }} mr-1 text-xs"></i>
                                                {{ $isUnread ? 'Unread' : 'Read' }}
                                            </span>
                                        </div>
                                    </td>
                                    
                                    <td class="p-3 align-top">
                                        <div class="font-semibold text-sm" style="color: var(--text-primary);">
                                            {{ $notification->title ?? ($notification->data['title'] ?? 'Notification') }}
                                        </div>
                                        <div class="text-sm mt-1" style="color: var(--text-secondary);">
                                            {{ $notification->message ?? ($notification->data['message'] ?? '') }}
                                        </div>
                                        @if(isset($notification->data['action_url']) || ($notification->action_url ?? false))
                                        <div class="mt-2">
                                            <a href="{{ $notification->action_url ?? $notification->data['action_url'] }}" 
                                               class="text-xs inline-flex items-center hover:underline" 
                                               style="color: {{ $roleColor }};">
                                                <i class="fas fa-external-link-alt mr-1 text-xs"></i>
                                                View Details
                                            </a>
                                        </div>
                                        @endif
                                        @if($canUndo && $isUnread)
                                        <div class="mt-2 text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-undo-alt mr-1"></i> Can undo within 5 minutes
                                        </div>
                                        @endif
                                    </td>
                                    
                                    <td class="p-3 align-top">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                              style="background-color: {{ $categoryConfig['bg'] }}; 
                                                     color: {{ $categoryConfig['text'] }};
                                                     border: 1px solid {{ $categoryConfig['border'] }};">
                                            <i class="fas {{ $categoryConfig['icon'] }} mr-1 text-xs"></i>
                                            {{ $category }}
                                        </span>
                                    </td>
                                    
                                    <td class="p-3 align-top">
                                        <div class="text-sm font-medium" style="color: var(--text-primary);">
                                            {{ $notification->created_at->format('M j, Y') }}
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            {{ $notification->created_at->format('g:i A') }}
                                        </div>
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="far fa-clock mr-1"></i> {{ $notification->created_at->diffForHumans() }}
                                        </div>
                                        @if($notification->read_at)
                                        <div class="mt-2 px-2 py-1 rounded text-xs" 
                                             style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                                            <i class="fas fa-check mr-1 text-xs"></i>
                                            Read {{ $notification->read_at->diffForHumans() }}
                                        </div>
                                        @endif
                                        @if($notification->seen_at ?? false)
                                        <div class="mt-1 px-2 py-0.5 rounded text-xs" 
                                             style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                            <i class="fas fa-eye mr-1 text-xs"></i>
                                            Seen {{ \Carbon\Carbon::parse($notification->seen_at)->diffForHumans() }}
                                        </div>
                                        @endif
                                    </td>
                                    
                                    <td class="p-3 align-top">
                                        <div class="flex flex-wrap gap-1">
                                            @if($isUnread)
                                            <button type="button" 
                                                    class="mark-as-read-btn action-btn" 
                                                    data-tooltip="Mark as Read"
                                                    data-id="{{ $notification->id }}">
                                                <i class="fas fa-envelope-open"></i>
                                            </button>
                                            @else
                                            <button type="button" 
                                                    class="mark-as-unread-btn action-btn" 
                                                    data-tooltip="Mark as Unread"
                                                    data-id="{{ $notification->id }}">
                                                <i class="fas fa-envelope"></i>
                                            </button>
                                            @endif
                                            
                                            @if(isset($notification->data['action_url']) || ($notification->action_url ?? false))
                                            <a href="{{ $notification->action_url ?? $notification->data['action_url'] }}" 
                                               class="action-btn view" 
                                               data-tooltip="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @endif
                                            
                                            <button type="button" 
                                                    class="delete-notification-btn action-btn delete" 
                                                    data-tooltip="Delete"
                                                    data-id="{{ $notification->id }}">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col md:flex-row justify-between items-center pt-6 mt-6 border-t" style="border-color: var(--border-color);">
                        <div class="text-sm mb-4 md:mb-0" style="color: var(--text-secondary);">
                            Showing {{ $notifications->firstItem() }} to {{ $notifications->lastItem() }} of {{ $notifications->total() }} entries
                        </div>
                        <div class="pagination">
                            {{ $notifications->appends(request()->except('page'))->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Hidden forms for actions -->
<form id="markAllReadForm" method="POST" action="{{ route('api.notifications.read-all') }}{{ $roleParam }}" style="display: none;">
    @csrf
</form>

<form id="clearAllForm" method="POST" action="{{ route('api.notifications.clear-all') }}{{ $roleParam }}" style="display: none;">
    @csrf
    @method('DELETE')
</form>

<form id="clearReadForm" method="POST" action="{{ route('api.notifications.clear-read') }}{{ $roleParam }}" style="display: none;">
    @csrf
    @method('DELETE')
</form>

<form id="exportForm" method="GET" action="{{ route('api.notifications.export') }}">
    <input type="hidden" name="format" id="exportFormat" value="csv">
    <input type="hidden" name="search" value="{{ $searchQuery }}">
    <input type="hidden" name="category" value="{{ $currentCategory }}">
    <input type="hidden" name="priority" value="{{ $currentPriority }}">
    <input type="hidden" name="from_date" value="{{ $dateFrom }}">
    <input type="hidden" name="to_date" value="{{ $dateTo }}">
    <input type="hidden" name="filter" value="{{ $currentFilter }}">
    @if($currentSelectedRole)
    <input type="hidden" name="role" value="{{ $currentSelectedRole }}">
    @endif
</form>

<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="current-role" content="{{ $currentSelectedRole }}">
@endsection

@push('scripts')
<script>
let currentDeleteId = null;
let selectedNotifications = new Set();
const currentRole = document.querySelector('meta[name="current-role"]')?.getAttribute('content') || '';

// Helper function to add role parameter to URLs
function addRoleToUrl(url) {
    if (!currentRole) return url;
    const separator = url.includes('?') ? '&' : '?';
    return url + separator + 'role=' + encodeURIComponent(currentRole);
}

// Helper function for API calls
function apiRequest(url, options = {}) {
    const fullUrl = addRoleToUrl(url);
    return fetch(fullUrl, {
        ...options,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...options.headers
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initTooltips();
    autoHideMessages();
    
    // Mark all as read button
    const markAllReadBtn = document.getElementById('markAllReadBtn');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function() {
            if (confirm('Mark all notifications as read?')) {
                submitMarkAllRead();
            }
        });
    }
    
    // Clear all button
    const clearAllBtn = document.getElementById('clearAllBtn');
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to clear ALL notifications? This action cannot be undone.')) {
                submitClearAll();
            }
        });
    }
    
    // Clear read button
    const clearReadBtn = document.getElementById('clearReadBtn');
    if (clearReadBtn) {
        clearReadBtn.addEventListener('click', function() {
            if (confirm('Delete all read notifications? This action cannot be undone.')) {
                submitClearRead();
            }
        });
    }
    
    // Export button
    const exportBtn = document.getElementById('exportBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            const format = confirm('Export as CSV? Click OK for CSV, Cancel for JSON') ? 'csv' : 'json';
            submitExport(format);
        });
    }
    
    // Mark as read buttons
    document.querySelectorAll('.mark-as-read-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const notificationId = this.dataset.id;
            if (notificationId) {
                markAsRead(notificationId, this);
            }
        });
    });
    
    // Mark as unread buttons
    document.querySelectorAll('.mark-as-unread-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const notificationId = this.dataset.id;
            if (notificationId) {
                markAsUnread(notificationId, this);
            }
        });
    });
    
    // Delete buttons - FIXED with proper error handling
    document.querySelectorAll('.delete-notification-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const notificationId = this.dataset.id;
            if (notificationId && confirm('Delete this notification?')) {
                deleteNotification(notificationId, this);
            }
        });
    });
    
    // Select All checkbox
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.notification-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
                if (this.checked) {
                    selectedNotifications.add(checkbox.dataset.id);
                } else {
                    selectedNotifications.clear();
                }
            });
            updateBulkActionBar();
        });
    }
    
    // Individual checkboxes
    document.querySelectorAll('.notification-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                selectedNotifications.add(this.dataset.id);
            } else {
                selectedNotifications.delete(this.dataset.id);
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = false;
                }
            }
            updateBulkActionBar();
        });
    });
    
    // Bulk actions
    const bulkMarkReadBtn = document.getElementById('bulkMarkReadBtn');
    if (bulkMarkReadBtn) {
        bulkMarkReadBtn.addEventListener('click', () => bulkMarkAsRead());
    }
    
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', () => bulkDelete());
    }
    
    const cancelBulkBtn = document.getElementById('cancelBulkBtn');
    if (cancelBulkBtn) {
        cancelBulkBtn.addEventListener('click', () => toggleBulkActionMode());
    }
});

function toggleBulkActionMode() {
    const bulkBar = document.getElementById('bulkActionBar');
    if (bulkBar) {
        bulkBar.classList.toggle('hidden');
        if (!bulkBar.classList.contains('hidden')) {
            selectedNotifications.clear();
            document.querySelectorAll('.notification-checkbox').forEach(cb => cb.checked = false);
            if (document.getElementById('selectAllCheckbox')) {
                document.getElementById('selectAllCheckbox').checked = false;
            }
            updateBulkActionBar();
        }
    }
}

function updateBulkActionBar() {
    const count = selectedNotifications.size;
    const countSpan = document.getElementById('selectedCount');
    if (countSpan) {
        countSpan.textContent = count;
    }
    
    const bulkBar = document.getElementById('bulkActionBar');
    if (bulkBar && !bulkBar.classList.contains('hidden')) {
        const markBtn = document.getElementById('bulkMarkReadBtn');
        const deleteBtn = document.getElementById('bulkDeleteBtn');
        
        if (markBtn) markBtn.disabled = count === 0;
        if (deleteBtn) deleteBtn.disabled = count === 0;
    }
}

function bulkMarkAsRead() {
    if (selectedNotifications.size === 0) return;
    
    if (confirm(`Mark ${selectedNotifications.size} notification(s) as read?`)) {
        const notificationIds = Array.from(selectedNotifications);
        
        apiRequest('/api/notifications/bulk/read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notification_ids: notificationIds })
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.message || 'Failed to mark notifications as read');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showNotification('success', data.message || `${data.count} notification(s) marked as read`);
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showNotification('error', data.message || 'Failed to mark notifications as read');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', error.message || 'An error occurred');
        });
    }
}

function bulkDelete() {
    if (selectedNotifications.size === 0) return;
    
    if (confirm(`Delete ${selectedNotifications.size} notification(s)? This action cannot be undone.`)) {
        const notificationIds = Array.from(selectedNotifications);
        
        apiRequest('/api/notifications/bulk', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notification_ids: notificationIds })
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.message || 'Failed to delete notifications');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showNotification('success', data.message || `${data.count} notification(s) deleted`);
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showNotification('error', data.message || 'Failed to delete notifications');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('error', error.message || 'An error occurred');
        });
    }
}

function updatePerPage(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', value);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

function submitMarkAllRead() {
    const form = document.getElementById('markAllReadForm');
    const submitBtn = document.getElementById('markAllReadBtn');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Processing...';
    submitBtn.disabled = true;
    
    apiRequest(form.action, {
        method: 'POST',
        body: new FormData(form)
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.message || 'Failed to mark notifications as read');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showNotification('success', data.message || 'All notifications marked as read');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification('error', data.message || 'Failed to mark notifications as read');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', error.message || 'An error occurred while processing the request');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

function submitClearAll() {
    const form = document.getElementById('clearAllForm');
    const submitBtn = document.getElementById('clearAllBtn');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Processing...';
    submitBtn.disabled = true;
    
    apiRequest(form.action, { method: 'DELETE' })
    .then(response => {
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.message || 'Failed to clear notifications');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showNotification('success', data.message || 'All notifications cleared');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification('error', data.message || 'Failed to clear notifications');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', error.message || 'An error occurred');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

function submitClearRead() {
    const form = document.getElementById('clearReadForm');
    const submitBtn = document.getElementById('clearReadBtn');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Processing...';
    submitBtn.disabled = true;
    
    apiRequest(form.action, { method: 'DELETE' })
    .then(response => {
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.message || 'Failed to clear read notifications');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showNotification('success', data.message || 'Read notifications cleared');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification('error', data.message || 'Failed to clear read notifications');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', error.message || 'An error occurred');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

function submitExport(format) {
    const form = document.getElementById('exportForm');
    const formatInput = document.getElementById('exportFormat');
    if (formatInput) {
        formatInput.value = format;
    }
    form.submit();
}

function markAsRead(notificationId, button) {
    const originalIcon = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    button.disabled = true;
    
    apiRequest(`/api/notifications/${notificationId}/read`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({})
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.message || 'Failed to mark as read');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showNotification('success', 'Notification marked as read');
            
            const row = button.closest('tr');
            const statusCell = row.querySelector('td:nth-child(2)');
            const statusBadge = statusCell.querySelector('span:last-child');
            
            statusBadge.innerHTML = '<i class="fas fa-envelope-open mr-1 text-xs"></i> Read';
            statusBadge.style.backgroundColor = 'rgba(var(--success-rgb), 0.1)';
            statusBadge.style.color = 'var(--success)';
            statusBadge.style.borderColor = 'rgba(var(--success-rgb), 0.3)';
            
            row.style.backgroundColor = 'transparent';
            row.dataset.unread = 'false';
            
            const actionsCell = row.querySelector('td:last-child');
            const markReadBtn = actionsCell.querySelector('.mark-as-read-btn');
            if (markReadBtn) {
                markReadBtn.classList.remove('mark-as-read-btn');
                markReadBtn.classList.add('mark-as-unread-btn');
                markReadBtn.innerHTML = '<i class="fas fa-envelope"></i>';
                markReadBtn.setAttribute('data-tooltip', 'Mark as Unread');
                
                markReadBtn.replaceWith(markReadBtn.cloneNode(true));
                const newBtn = actionsCell.querySelector('.mark-as-unread-btn');
                newBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    markAsUnread(newBtn.dataset.id, newBtn);
                });
            }
            
            refreshCounts();
        } else {
            showNotification('error', data.message || 'Failed to mark as read');
            button.innerHTML = originalIcon;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', error.message || 'An error occurred');
        button.innerHTML = originalIcon;
        button.disabled = false;
    });
}

function markAsUnread(notificationId, button) {
    const originalIcon = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    button.disabled = true;
    
    apiRequest(`/api/notifications/${notificationId}/unread`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({})
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.message || 'Failed to mark as unread');
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showNotification('success', 'Notification marked as unread');
            
            const row = button.closest('tr');
            const statusCell = row.querySelector('td:nth-child(2)');
            const statusBadge = statusCell.querySelector('span:last-child');
            
            statusBadge.innerHTML = '<i class="fas fa-envelope mr-1 text-xs"></i> Unread';
            statusBadge.style.backgroundColor = 'rgba(var(--primary-rgb), 0.1)';
            statusBadge.style.color = 'var(--primary)';
            statusBadge.style.borderColor = 'rgba(var(--primary-rgb), 0.3)';
            
            row.style.backgroundColor = 'rgba(var(--primary-rgb), 0.03)';
            row.dataset.unread = 'true';
            
            const actionsCell = row.querySelector('td:last-child');
            const markUnreadBtn = actionsCell.querySelector('.mark-as-unread-btn');
            if (markUnreadBtn) {
                markUnreadBtn.classList.remove('mark-as-unread-btn');
                markUnreadBtn.classList.add('mark-as-read-btn');
                markUnreadBtn.innerHTML = '<i class="fas fa-envelope-open"></i>';
                markUnreadBtn.setAttribute('data-tooltip', 'Mark as Read');
                
                markUnreadBtn.replaceWith(markUnreadBtn.cloneNode(true));
                const newBtn = actionsCell.querySelector('.mark-as-read-btn');
                newBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    markAsRead(newBtn.dataset.id, newBtn);
                });
            }
            
            refreshCounts();
        } else {
            showNotification('error', data.message || 'Failed to mark as unread');
            button.innerHTML = originalIcon;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', error.message || 'An error occurred');
        button.innerHTML = originalIcon;
        button.disabled = false;
    });
}

// ✅ FIXED: deleteNotification function with proper error handling
function deleteNotification(notificationId, button) {
    const originalIcon = button.innerHTML;
    const row = button.closest('tr');
    
    // Show loading state
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    button.disabled = true;
    
    apiRequest(`/api/notifications/${notificationId}`, { 
        method: 'DELETE'
    })
    .then(response => {
        // Always try to parse JSON first
        return response.json().then(data => {
            if (!response.ok) {
                throw new Error(data.message || 'Failed to delete notification');
            }
            return data;
        });
    })
    .then(data => {
        if (data.success) {
            showNotification('success', data.message || 'Notification deleted successfully');
            
            // Animate row removal
            row.style.transition = 'all 0.3s ease';
            row.style.opacity = '0';
            row.style.transform = 'translateX(-20px)';
            
            setTimeout(() => {
                row.remove();
                
                // Update counts
                refreshCounts();
                
                // Check if table is empty
                const remainingRows = document.querySelectorAll('#notificationsTableBody tr').length;
                if (remainingRows === 0) {
                    // Reload page after a delay to show empty state
                    setTimeout(() => window.location.reload(), 500);
                }
            }, 300);
        } else {
            showNotification('error', data.message || 'Failed to delete notification');
            button.innerHTML = originalIcon;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error deleting notification:', error);
        showNotification('error', error.message || 'An error occurred while deleting the notification');
        button.innerHTML = originalIcon;
        button.disabled = false;
    });
}

function refreshCounts() {
    apiRequest('/api/notifications/count', { headers: { 'Accept': 'application/json' } })
    .then(response => {
        if (!response.ok) {
            throw new Error('Failed to fetch counts');
        }
        return response.json();
    })
    .then(data => {
        const totalCount = data.total || 0;
        const unreadCount = data.unread_count || 0;
        const readCount = totalCount - unreadCount;
        
        const totalEl = document.getElementById('totalCount');
        const unreadEl = document.getElementById('unreadCount');
        const readEl = document.getElementById('readCount');
        
        if (totalEl) totalEl.textContent = totalCount.toLocaleString();
        if (unreadEl) unreadEl.textContent = unreadCount.toLocaleString();
        if (readEl) readEl.textContent = readCount.toLocaleString();
        
        const markAllBtn = document.getElementById('markAllReadBtn');
        if (markAllBtn) {
            if (unreadCount === 0) {
                markAllBtn.style.display = 'none';
            } else {
                markAllBtn.style.display = 'inline-flex';
            }
        }
    })
    .catch(error => {
        console.error('Error refreshing counts:', error);
    });
}

function showNotification(type, message) {
    // Create container if it doesn't exist
    let container = document.getElementById('notificationContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notificationContainer';
        container.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
            width: 100%;
            pointer-events: none;
        `;
        document.body.appendChild(container);
    }
    
    const notification = document.createElement('div');
    notification.style.cssText = `
        margin-bottom: 0.75rem;
        padding: 1rem 1.25rem;
        border-radius: 0.5rem;
        background-color: ${type === 'success' ? 'rgba(var(--success-rgb), 0.95)' : 'rgba(var(--danger-rgb), 0.95)'};
        border: 1px solid ${type === 'success' ? 'rgba(var(--success-rgb), 0.3)' : 'rgba(var(--danger-rgb), 0.3)'};
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        animation: slideIn 0.3s ease;
        pointer-events: auto;
        backdrop-filter: blur(10px);
    `;
    notification.innerHTML = `
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2" 
                   style="color: ${type === 'success' ? 'var(--success)' : 'var(--danger)'};"></i>
                <span style="color: white;">${escapeHtml(message)}</span>
            </div>
            <button type="button" onclick="this.parentElement.parentElement.remove()" 
                    style="color: rgba(255,255,255,0.7); background: none; border: none; cursor: pointer; padding: 0.25rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    container.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(20px)';
            notification.style.transition = 'all 0.3s ease';
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 300);
        }
    }, 5000);
}

function autoHideMessages() {
    setTimeout(() => {
        document.querySelectorAll('.success-message, .error-message').forEach(msg => {
            if (msg.style.display !== 'none') msg.style.display = 'none';
        });
    }, 5000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function initTooltips() {
    document.querySelectorAll('[data-tooltip]').forEach(element => {
        element.addEventListener('mouseenter', function(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.getAttribute('data-tooltip');
            tooltip.style.cssText = `
                position: absolute;
                background: var(--text-primary);
                color: var(--card-bg);
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                z-index: 1000;
                white-space: nowrap;
                pointer-events: none;
            `;
            document.body.appendChild(tooltip);
            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
            tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
            this._tooltip = tooltip;
        });
        
        element.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                this._tooltip = null;
            }
        });
    });
}

// Add animation styles
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .fa-spinner { animation: spin 1s linear infinite; }
    .active-filter { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; }
    .action-btn {
        padding: 0.5rem;
        border-radius: 6px;
        font-size: 0.75rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        border: none;
        cursor: pointer;
        min-width: 32px;
        min-height: 32px;
    }
    .action-btn.view {
        background-color: rgba(var(--info-rgb), 0.1);
        color: var(--info);
        border: 1px solid rgba(var(--info-rgb), 0.3);
    }
    .action-btn.delete {
        background-color: rgba(var(--danger-rgb), 0.1);
        color: var(--danger);
        border: 1px solid rgba(var(--danger-rgb), 0.3);
    }
    .action-btn:hover { transform: translateY(-1px); }
    .action-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
    .index-custom-dropdown {
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        border-radius: 0.375rem;
        padding: 0.5rem 0.75rem;
    }
    .index-custom-dropdown:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
    }
    .btn-primary { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; }
    .btn-secondary {
        background-color: rgba(var(--secondary-rgb), 0.1);
        color: var(--secondary);
        border: 1px solid rgba(var(--secondary-rgb), 0.3);
    }
    .badge-primary {
        background-color: rgba(var(--primary-rgb), 0.1);
        color: var(--primary);
        border: 1px solid rgba(var(--primary-rgb), 0.3);
    }
    .tooltip { pointer-events: none; }
    @media (max-width: 768px) { .action-btn { padding: 0.375rem; } }
`;
document.head.appendChild(style);
</script>
@endpush