{{-- developer/super-admins/index.blade.php --}}
@php
    $isDeveloper = auth()->user()->isDeveloper();
    $isAdmin = auth()->user()->isAdmin();
    $isSuperAdmin = auth()->user()->isSuperAdmin();
    
    // Set read-only mode for Super Admins
    $isReadOnly = $isSuperAdmin;
    
    // Determine route prefix and layout based on user role
    if ($isSuperAdmin) {
        $routeNamePrefix = 'developer.super-admins';  // Route name (with dots)
        $urlPath = 'developer/super-admins';          // URL path (with slashes)
        $layout = 'layouts.app';
        $pageTitle = 'Super Admins Overview - Super Admin Portal';
        $backRoute = route('super-admin.dashboard');
        $backIcon = 'fa-dashboard';
        $backText = 'Dashboard';
        $viewDescription = 'Overview of all Super Admin accounts in the system';
    } elseif ($isDeveloper) {
        $routeNamePrefix = 'developer.super-admins';
        $urlPath = 'developer/super-admins';
        $layout = 'layouts.dev';
        $pageTitle = 'Super Admins Management - Developer Portal';
        $backRoute = route('developer.dashboard');
        $backIcon = 'fa-dashboard';
        $backText = 'Dashboard';
        $viewDescription = 'Manage Super Admin accounts you created';
    } else {
        $routeNamePrefix = 'developer.super-admins';
        $urlPath = 'developer/super-admins';
        $layout = 'layouts.admin';
        $pageTitle = 'Super Admins Management - Admin Portal';
        $backRoute = route('admin.dashboard');
        $backIcon = 'fa-dashboard';
        $backText = 'Dashboard';
        $viewDescription = 'Manage Super Admin accounts';
    }
    
    $successMessage = session('success');
    $errorMessage = session('error');
    $bulkSuccess = session('bulk_success');
    $bulkError = session('bulk_error');
    $validationErrors = session('errors');
    $invitationResult = session('invitation_result');
    
    $statuses = $statuses ?? [];
    $statistics = $statistics ?? [];
    $superAdmins = $superAdmins ?? collect();
    $emailConfigStatus = $emailConfigStatus ?? [
        'can_send' => false,
        'message' => 'Email configuration status unknown',
        'config_source' => 'service'
    ];
    
    $developerEmailConfigured = $emailConfigStatus['can_send'] ?? false;
    
    $filterStatus = request('status', 'all');
    $searchTerm = request('search', '');
    $dateFrom = request('date_from', '');
    $dateTo = request('date_to', '');
    
    // Determine permissions
    $canCreate = $isDeveloper && !$isReadOnly;
    $canEdit = $isDeveloper && !$isReadOnly;
    $canDelete = $isDeveloper && !$isReadOnly;
    $canInvite = ($isDeveloper && $developerEmailConfigured) && !$isReadOnly;
    $canConfigureEmail = $isDeveloper;
    $canExport = $isDeveloper && !$isReadOnly;
    $canViewTrash = $isDeveloper && !$isReadOnly;
    $canManageOwnRoles = $isSuperAdmin;
    // NEW: Super Admins can view other Super Admins
    $canView = $isSuperAdmin || $isDeveloper || $isAdmin;
    
    $hasTrashedItems = \App\Models\User::onlyTrashed()
        ->where('type', \App\Models\User::TYPE_SUPER_ADMIN)
        ->where('created_by', auth()->id())
        ->count() > 0;
@endphp

@extends($layout)

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-color: var(--primary);">
                        <i class="fas fa-user-shield text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-shield mr-2" style="color: var(--primary);"></i> 
                        {{ $isSuperAdmin ? 'Super Admins Overview' : 'Super Admins Management' }}
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>{{ $viewDescription }}</span>
                        @if(isset($statistics['total']))
                        <span class="mx-2">•</span>
                        <i class="fas fa-chart-bar mr-1"></i>
                        <span>{{ $statistics['total'] }} super admins total</span>
                        @endif
                        @if($developerEmailConfigured && !$isReadOnly)
                        <span class="mx-2">•</span>
                        <i class="fas fa-envelope mr-1 text-green-500"></i>
                        <span class="text-green-600 font-medium">Email Ready</span>
                        @elseif(!$developerEmailConfigured && !$isReadOnly)
                        <span class="mx-2">•</span>
                        <i class="fas fa-exclamation-triangle mr-1 text-yellow-500"></i>
                        <span class="text-yellow-600 font-medium">Setup Email First</span>
                        @endif
                        
                        @if($isReadOnly)
                        <span class="mx-2">•</span>
                        <i class="fas fa-eye mr-1 text-blue-500"></i>
                        <span class="text-blue-600 font-medium">Read-Only View</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                <a href="{{ $backRoute }}" 
                   class="ml-3 px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas {{ $backIcon }} mr-1"></i> {{ $backText }}
                </a>
                @if($canViewTrash)
                <a href="{{ route($routeNamePrefix . '.trash') }}" 
                   class="ml-3 px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-trash-restore mr-1"></i> Trash 
                    @if($hasTrashedItems)
                    <span class="ml-1 px-1.5 py-0.5 rounded-full text-xs" style="background-color: var(--warning); color: white;">{{ $statistics['trashed'] ?? 0 }}</span>
                    @endif
                </a>
                @endif
            </div>
        </div>
    </div>

    <!-- Success/Error Messages (only for non-read-only) -->
    @if(!$isReadOnly)
    @if($successMessage)
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
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
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
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

    <!-- Invitation Success Alert -->
    @if($invitationResult && $invitationResult['success'])
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span class="font-bold">Invitation Sent!</span>
            <span class="ml-2">{{ $invitationResult['message'] ?? 'Email invitation sent successfully' }}</span>
        </div>
        @if(isset($invitationResult['invitation_url']))
        <div class="mt-2 ml-6 text-sm">
            <p class="mb-1"><strong>Invitation URL:</strong></p>
            <div class="flex items-center">
                <input type="text" 
                       value="{{ $invitationResult['invitation_url'] }}" 
                       class="flex-1 bg-gray-50 border border-gray-300 rounded px-2 py-1 text-xs font-mono"
                       readonly>
                <button type="button" 
                        onclick="copyToClipboard('{{ $invitationResult['invitation_url'] }}')"
                        class="ml-2 px-2 py-1 text-xs font-medium rounded bg-blue-500 text-white hover:bg-blue-600">
                    <i class="fas fa-copy mr-1"></i> Copy
                </button>
            </div>
        </div>
        @endif
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif
    @endif

    <!-- Navigation Card (only for developers) -->
    @if(!$isReadOnly)
    <div class="card">
        <div class="p-6">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center space-x-3 flex-wrap gap-2">
                    @if($canCreate)
                    <a href="{{ route($routeNamePrefix . '.create') }}" 
                       class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium text-white bg-gradient-to-r from-purple-500 to-indigo-600 hover:from-purple-600 hover:to-indigo-700 transition-all">
                        <i class="fas fa-plus-circle mr-2"></i> Create Super Admin
                    </a>
                    @endif
                    
                    @if($canViewTrash)
                    <a href="{{ route($routeNamePrefix . '.trash') }}" 
                       class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium" 
                       style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                        <i class="fas fa-trash-restore mr-2"></i> Trash 
                        @if($hasTrashedItems)
                        <span class="ml-1 px-2 py-0.5 rounded-full text-xs" style="background-color: var(--warning); color: white;">{{ $statistics['trashed'] ?? 0 }}</span>
                        @endif
                    </a>
                    @endif
                    
                    @if($developerEmailConfigured)
                    <span class="text-xs px-3 py-1 rounded-full badge-success">
                        <i class="fas fa-check-circle mr-1"></i> Email Ready
                    </span>
                    @elseif($canConfigureEmail)
                    <a href="{{ route('developer.settings.index', ['section' => 'email']) }}" 
                       class="text-xs px-3 py-1 rounded-full badge-warning inline-flex items-center">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Setup Email
                    </a>
                    @endif
                </div>
                
                <div class="flex items-center space-x-3 flex-wrap gap-2">
                    @if(request()->hasAny(['search', 'status', 'date_from', 'date_to']))
                    <span class="text-xs px-3 py-1 rounded-full" 
                          style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-filter mr-1"></i> Filters Applied
                    </span>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Email Configuration Alert (only for developers) -->
    @if(!$developerEmailConfigured && $canConfigureEmail && !$isReadOnly)
    <div class="card border-l-4" style="border-left-color: var(--warning); background-color: rgba(var(--warning-rgb), 0.05);">
        <div class="p-6">
            <div class="flex items-start flex-wrap gap-4">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-exclamation-triangle text-lg" style="color: var(--warning);"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <h4 class="font-semibold mb-1 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-exclamation-circle mr-2"></i> Email Configuration Required
                    </h4>
                    <p class="text-sm mb-3" style="color: var(--text-secondary);">
                        Email invitations cannot be sent until email configuration is completed.
                        You can still create accounts with manual password.
                    </p>
                    <a href="{{ route('developer.settings.index', ['section' => 'email']) }}" 
                       class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-white bg-yellow-500 hover:bg-yellow-600">
                        <i class="fas fa-cog mr-2"></i> Setup Email Now
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Statistics Cards -->
    @if(isset($statistics) && !empty($statistics))
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
        <!-- Total Card -->
        <div class="card p-4 text-center">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" style="background-color: rgba(var(--primary-rgb), 0.1);">
                <i class="fas fa-users text-lg" style="color: var(--primary);"></i>
            </div>
            <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($statistics['total'] ?? 0) }}</p>
            <p class="text-xs" style="color: var(--text-secondary);">Total</p>
        </div>
        
        <!-- Active Card -->
        <div class="card p-4 text-center">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" style="background-color: rgba(var(--success-rgb), 0.1);">
                <i class="fas fa-user-check text-lg" style="color: var(--success);"></i>
            </div>
            <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($statistics['active'] ?? 0) }}</p>
            <p class="text-xs" style="color: var(--text-secondary);">Active</p>
        </div>
        
        <!-- Pending Card -->
        <div class="card p-4 text-center">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" style="background-color: rgba(var(--warning-rgb), 0.1);">
                <i class="fas fa-user-clock text-lg" style="color: var(--warning);"></i>
            </div>
            <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($statistics['pending'] ?? 0) }}</p>
            <p class="text-xs" style="color: var(--text-secondary);">Pending</p>
        </div>
        
        <!-- Suspended Card -->
        <div class="card p-4 text-center">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" style="background-color: rgba(var(--danger-rgb), 0.1);">
                <i class="fas fa-user-slash text-lg" style="color: var(--danger);"></i>
            </div>
            <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($statistics['suspended'] ?? 0) }}</p>
            <p class="text-xs" style="color: var(--text-secondary);">Suspended</p>
        </div>
        
        <!-- Inactive Card -->
        <div class="card p-4 text-center">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" style="background-color: rgba(var(--secondary-rgb), 0.1);">
                <i class="fas fa-user-times text-lg" style="color: var(--secondary);"></i>
            </div>
            <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($statistics['inactive'] ?? 0) }}</p>
            <p class="text-xs" style="color: var(--text-secondary);">Inactive</p>
        </div>
        
        <!-- Trashed Card -->
        <div class="card p-4 text-center">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" style="background-color: rgba(var(--warning-rgb), 0.1);">
                <i class="fas fa-trash-alt text-lg" style="color: var(--warning);"></i>
            </div>
            <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($statistics['trashed'] ?? 0) }}</p>
            <p class="text-xs" style="color: var(--text-secondary);">Trashed</p>
        </div>
    </div>
    @endif

    <!-- Main Content -->
    <div class="grid grid-cols-1 {{ $isReadOnly ? 'lg:grid-cols-1' : 'lg:grid-cols-4' }} gap-6">
        <!-- Filters Sidebar (only for developers) -->
        @if(!$isReadOnly)
        <div class="lg:col-span-1">
            <div class="card">
                <div class="p-5">
                    <h3 class="text-md font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filters
                    </h3>
                    
                    <form method="GET" action="{{ route($routeNamePrefix . '.index') }}" class="space-y-4">
                        <!-- Search -->
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-search mr-1"></i> Search
                            </label>
                            <input type="text" 
                                   name="search" 
                                   value="{{ $searchTerm }}" 
                                   class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                                   style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                                   placeholder="Name, email, phone, username...">
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-tag mr-1"></i> Status
                            </label>
                            <select name="status" class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                                    style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                                <option value="all" {{ $filterStatus == 'all' ? 'selected' : '' }}>All Status</option>
                                @foreach($statuses as $key => $label)
                                    <option value="{{ $key }}" {{ $filterStatus == $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Date Range -->
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-calendar mr-1"></i> Date Range
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="date" 
                                       name="date_from" 
                                       value="{{ $dateFrom }}" 
                                       class="px-3 py-2 rounded-lg border focus:outline-none focus:ring-2 text-sm"
                                       style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                                <input type="date" 
                                       name="date_to" 
                                       value="{{ $dateTo }}" 
                                       class="px-3 py-2 rounded-lg border focus:outline-none focus:ring-2 text-sm"
                                       style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                            </div>
                        </div>

                        <!-- Per Page -->
                        <div>
                            <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                <i class="fas fa-table mr-1"></i> Per Page
                            </label>
                            <select name="per_page" class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                                    style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                                    onchange="this.form.submit()">
                                <option value="10" {{ request('per_page', 20) == 10 ? 'selected' : '' }}>10</option>
                                <option value="20" {{ request('per_page', 20) == 20 ? 'selected' : '' }}>20</option>
                                <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                            </select>
                        </div>

                        <!-- Action Buttons -->
                        <div class="pt-4 border-t" style="border-color: var(--border-color);">
                            <div class="space-y-2">
                                <button type="submit" 
                                        class="block w-full text-center px-4 py-2 rounded-lg font-medium text-white"
                                        style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                                    <i class="fas fa-filter mr-2"></i> Apply Filters
                                </button>
                                
                                <a href="{{ route($routeNamePrefix . '.index') }}" 
                                   class="block w-full text-center px-4 py-2 rounded-lg font-medium text-center"
                                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                                    <i class="fas fa-redo mr-2"></i> Reset Filters
                                </a>
                                
                                @if($canExport && $superAdmins->total() > 0)
                                <a href="{{ route($routeNamePrefix . '.index', array_merge(request()->all(), ['export' => 'csv'])) }}" 
                                   class="block w-full text-center px-4 py-2 rounded-lg font-medium text-white"
                                   style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                                    <i class="fas fa-file-export mr-2"></i> Export CSV
                                </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        <!-- Super Admins Table -->
        <div class="{{ $isReadOnly ? 'lg:col-span-1' : 'lg:col-span-3' }}">
            <div class="card">
                <div class="p-5">
                    <!-- Table Header -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-5 gap-3">
                        <div>
                            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                                {{ $isSuperAdmin ? 'All Super Admins' : 'Super Admins List' }}
                            </h3>
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                Showing {{ $superAdmins->firstItem() ?? 0 }} to {{ $superAdmins->lastItem() ?? 0 }} of {{ $superAdmins->total() }} entries
                            </p>
                        </div>
                        
                        @if(!$isReadOnly && $superAdmins->total() > 0)
                        <div class="flex items-center gap-2">
                            <!-- View Toggle -->
                            <div class="flex rounded-lg overflow-hidden border" style="border-color: var(--border-color);">
                                <button id="tableViewBtn" class="px-3 py-1.5 text-sm transition" style="background-color: var(--primary); color: white;">
                                    <i class="fas fa-table"></i>
                                </button>
                                <button id="cardViewBtn" class="px-3 py-1.5 text-sm transition" style="background-color: var(--card-bg); color: var(--text-secondary);">
                                    <i class="fas fa-th-large"></i>
                                </button>
                            </div>
                            
                            <!-- Bulk Actions -->
                            <button type="button" 
                                    onclick="showBulkActionsModal()"
                                    class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium"
                                    style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                <i class="fas fa-layer-group mr-2"></i> Bulk Actions
                            </button>
                        </div>
                        @endif
                    </div>

                    @if($superAdmins->isEmpty())
                        <!-- Empty State -->
                        <div class="text-center py-12">
                            <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" 
                                 style="background-color: rgba(var(--primary-rgb), 0.1);">
                                <i class="fas fa-user-shield text-2xl" style="color: var(--primary);"></i>
                            </div>
                            <h4 class="text-lg font-semibold mb-2" style="color: var(--text-primary);">
                                No Super Admins Found
                            </h4>
                            <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                                @if($isDeveloper)
                                    @if(request()->hasAny(['search', 'status', 'date_from', 'date_to']))
                                        No Super Admins match your filter criteria. Try adjusting your filters.
                                    @else
                                        You haven't created any Super Admin accounts yet. Start by creating your first Super Admin.
                                    @endif
                                @else
                                    No Super Admin accounts found in the system.
                                @endif
                            </p>
                            @if($canCreate && !request()->hasAny(['search', 'status', 'date_from', 'date_to']))
                            <a href="{{ route($routeNamePrefix . '.create') }}" 
                               class="inline-flex items-center px-4 py-2 rounded-lg font-medium text-white"
                               style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                                <i class="fas fa-user-plus mr-2"></i> Create First Super Admin
                            </a>
                            @elseif($canCreate && request()->hasAny(['search', 'status', 'date_from', 'date_to']))
                            <a href="{{ route($routeNamePrefix . '.index') }}" 
                               class="inline-flex items-center px-4 py-2 rounded-lg font-medium"
                               style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                <i class="fas fa-times mr-2"></i> Clear Filters
                            </a>
                            @endif
                        </div>
                    @else
                        <!-- Table View -->
                        <div id="tableView" class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr style="border-bottom-color: var(--border-color);">
                                        @if(!$isReadOnly)
                                        <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary); width: 40px;">
                                            <input type="checkbox" id="selectAll" class="rounded border-gray-300 dark:border-gray-600">
                                        </th>
                                        @endif
                                        <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Super Admin</th>
                                        <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Contact</th>
                                        <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Status</th>
                                        <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Created</th>
                                        @if($isSuperAdmin)
                                        <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">Created By</th>
                                        @endif
                                        <!-- ==================== FIXED: Actions column visible to Super Admins ==================== -->
                                        <th class="text-left p-3 font-medium text-xs uppercase tracking-wider" style="color: var(--text-secondary); background-color: var(--bg-secondary);">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($superAdmins as $superAdmin)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition" style="border-bottom-color: var(--border-color);">
                                        @if(!$isReadOnly)
                                        <td class="p-3" style="background-color: var(--card-bg);">
                                            <input type="checkbox" 
                                                   class="user-checkbox rounded border-gray-300 dark:border-gray-600" 
                                                   value="{{ $superAdmin->id }}">
                                          </td>
                                        @endif
                                        <td class="p-3" style="background-color: var(--card-bg);">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3 flex-shrink-0 text-sm font-semibold"
                                                     style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                                                    {{ $superAdmin->initials ?? strtoupper(substr($superAdmin->name, 0, 2)) }}
                                                </div>
                                                <div>
                                                    <div class="font-medium" style="color: var(--text-primary);">{{ $superAdmin->name }}</div>
                                                    <div class="text-xs" style="color: var(--text-secondary);">{{ $superAdmin->username ?? $superAdmin->email }}</div>
                                                    @if($superAdmin->id === auth()->id())
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 mt-1">
                                                        You
                                                    </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-3" style="background-color: var(--card-bg);">
                                            <div class="text-sm" style="color: var(--text-primary);">{{ $superAdmin->email }}</div>
                                            <div class="text-xs" style="color: var(--text-secondary);">{{ $superAdmin->phone ?? 'No phone' }}</div>
                                            @if($superAdmin->phone_verified_at)
                                            <span class="inline-flex items-center text-xs text-green-600 dark:text-green-400 mt-1">
                                                <i class="fas fa-check-circle mr-1 text-green-500"></i> Verified
                                            </span>
                                            @endif
                                        </td>
                                        <td class="p-3" style="background-color: var(--card-bg);">
                                            @php
                                                $statusColor = match($superAdmin->status) {
                                                    'active' => 'success',
                                                    'pending' => 'warning',
                                                    'suspended' => 'danger',
                                                    'inactive' => 'secondary',
                                                    default => 'secondary'
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ $statusColor }}">
                                                @if($superAdmin->status === 'active')
                                                    <i class="fas fa-circle mr-1 text-xs"></i>
                                                @elseif($superAdmin->status === 'pending')
                                                    <i class="fas fa-clock mr-1 text-xs"></i>
                                                @elseif($superAdmin->status === 'suspended')
                                                    <i class="fas fa-ban mr-1 text-xs"></i>
                                                @else
                                                    <i class="fas fa-minus-circle mr-1 text-xs"></i>
                                                @endif
                                                {{ ucfirst($superAdmin->status) }}
                                            </span>
                                            @if(!$superAdmin->email_verified_at && $superAdmin->status !== 'pending')
                                            <span class="inline-flex items-center ml-1 text-xs text-yellow-600 dark:text-yellow-400">
                                                <i class="fas fa-envelope"></i>
                                            </span>
                                            @endif
                                        </td>
                                        <td class="p-3" style="background-color: var(--card-bg);">
                                            <div class="text-sm" style="color: var(--text-primary);">{{ $superAdmin->created_at->format('M d, Y') }}</div>
                                            <div class="text-xs" style="color: var(--text-secondary);">{{ $superAdmin->created_at->diffForHumans() }}</div>
                                            @if($superAdmin->last_login_at)
                                            <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">Last login: {{ $superAdmin->last_login_at->diffForHumans() }}</div>
                                            @endif
                                        </td>
                                        @if($isSuperAdmin)
                                        <td class="p-3" style="background-color: var(--card-bg);">
                                            <div class="text-sm" style="color: var(--text-primary);">{{ $superAdmin->creator->name ?? 'System' }}</div>
                                            <div class="text-xs" style="color: var(--text-secondary);">{{ $superAdmin->creator->email ?? '' }}</div>
                                         </div>
                                        @endif
                                        <!-- ==================== FIXED: Actions column - visible to Super Admins ==================== -->
                                        <td class="p-3" style="background-color: var(--card-bg);">
                                            <div class="flex flex-wrap gap-1">
                                                <!-- View Button - Visible to ALL (Developers, Admins, AND Super Admins) -->
                                                @if($isSuperAdmin)
                                                    <a href="{{ route('super-admin.super-admins.show', $superAdmin->id) }}" 
                                                       class="action-btn view" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                @else
                                                    <a href="{{ route('developer.super-admins.show', $superAdmin->id) }}" 
                                                       class="action-btn view" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                @endif
                                                
                                                <!-- Edit Button - Only for Developers who created the user -->
                                                @if($canEdit && $superAdmin->created_by === auth()->id())
                                                <a href="{{ route($routeNamePrefix . '.edit', $superAdmin->id) }}" 
                                                   class="action-btn edit" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                @endif
                                                
                                                <!-- Invite Button - Only for Developers who created the user -->
                                                @if($canInvite && $superAdmin->status !== 'active' && $superAdmin->created_by === auth()->id())
                                                <button onclick="showInvitationModal({{ $superAdmin->id }}, '{{ addslashes($superAdmin->name) }}')" 
                                                        class="action-btn invite" title="Send Invitation">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                                @endif
                                                
                                                <!-- Delete Button - Only for Developers who created the user -->
                                                @if($canDelete && $superAdmin->id !== auth()->id() && $superAdmin->created_by === auth()->id())
                                                <button onclick="showDeleteModal({{ $superAdmin->id }}, '{{ addslashes($superAdmin->name) }}')" 
                                                        class="action-btn delete" title="Delete">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                                @endif
                                                
                                                <!-- Resend Invite - Only for Developers who created the user -->
                                                @if($canInvite && $superAdmin->status === 'pending' && $superAdmin->last_invitation_sent_at && $superAdmin->created_by === auth()->id())
                                                <button onclick="resendInvitation({{ $superAdmin->id }}, '{{ addslashes($superAdmin->name) }}')" 
                                                        class="action-btn resend" title="Resend Invitation">
                                                    <i class="fas fa-redo-alt"></i>
                                                </button>
                                                @endif
                                            </div>
                                            <!-- Hint for Super Admins about read-only access -->
                                            @if($isSuperAdmin)
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-info-circle mr-1"></i> View only
                                            </div>
                                            @endif
                                        </div>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Card View (Hidden by default) -->
                        @if(!$isReadOnly)
                        <div id="cardView" class="hidden grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                            @foreach($superAdmins as $superAdmin)
                            <div class="card hover:shadow-lg transition-all duration-300">
                                <div class="p-4">
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex items-center">
                                            <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3 flex-shrink-0 text-lg font-semibold"
                                                 style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                                                {{ $superAdmin->initials ?? strtoupper(substr($superAdmin->name, 0, 2)) }}
                                            </div>
                                            <div>
                                                <h4 class="font-semibold" style="color: var(--text-primary);">{{ $superAdmin->name }}</h4>
                                                <p class="text-xs" style="color: var(--text-secondary);">{{ $superAdmin->username ?? '' }}</p>
                                            </div>
                                        </div>
                                        <input type="checkbox" 
                                               class="user-checkbox mt-1 rounded border-gray-300 dark:border-gray-600" 
                                               value="{{ $superAdmin->id }}">
                                    </div>
                                    
                                    <div class="space-y-2 mb-4">
                                        <div class="flex items-center text-sm" style="color: var(--text-primary);">
                                            <i class="fas fa-envelope w-5 text-gray-400"></i>
                                            <span class="truncate">{{ $superAdmin->email }}</span>
                                        </div>
                                        <div class="flex items-center text-sm" style="color: var(--text-primary);">
                                            <i class="fas fa-phone w-5 text-gray-400"></i>
                                            <span>{{ $superAdmin->phone ?? 'No phone' }}</span>
                                        </div>
                                        <div class="flex items-center text-sm">
                                            <i class="fas fa-calendar w-5 text-gray-400"></i>
                                            <span style="color: var(--text-primary);">{{ $superAdmin->created_at->format('M d, Y') }}</span>
                                        </div>
                                        <div class="flex items-center text-sm">
                                            <i class="fas fa-clock w-5 text-gray-400"></i>
                                            <span class="text-xs" style="color: var(--text-secondary);">{{ $superAdmin->created_at->diffForHumans() }}</span>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center justify-between pt-3 border-t" style="border-color: var(--border-color);">
                                        @php
                                            $statusColor = match($superAdmin->status) {
                                                'active' => 'success',
                                                'pending' => 'warning',
                                                'suspended' => 'danger',
                                                'inactive' => 'secondary',
                                                default => 'secondary'
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ $statusColor }}">
                                            {{ ucfirst($superAdmin->status) }}
                                        </span>
                                        
                                        <div class="flex gap-1">
                                            <a href="{{ route($routeNamePrefix . '.show', $superAdmin->id) }}" 
                                               class="action-btn view" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @if($canEdit && $superAdmin->created_by === auth()->id())
                                            <a href="{{ route($routeNamePrefix . '.edit', $superAdmin->id) }}" 
                                               class="action-btn edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @endif

                        <!-- Pagination -->
                        <div class="flex flex-col sm:flex-row justify-between items-center gap-4 pt-6 mt-4 border-t" style="border-color: var(--border-color);">
                            <div class="text-sm" style="color: var(--text-secondary);">
                                Showing {{ $superAdmins->firstItem() }} to {{ $superAdmins->lastItem() }} of {{ $superAdmins->total() }} entries
                            </div>
                            <div>
                                {{ $superAdmins->appends(request()->except('page'))->links() }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals (only for developers) -->
@if(!$isReadOnly)

<!-- Bulk Actions Modal -->
<div id="bulkActionsModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70" onclick="hideBulkActionsModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="rounded-lg shadow-xl w-full max-w-md" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center p-5 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-layer-group mr-2" style="color: var(--primary);"></i> Bulk Actions
                </h3>
                <button type="button" onclick="hideBulkActionsModal()" class="transition" style="color: var(--text-secondary);">
                    <i class="fas fa-times hover:text-red-500"></i>
                </button>
            </div>
            <div class="p-5">
                <div class="mb-4">
                    <p class="text-sm" id="selectedCountText" style="color: var(--text-secondary);">
                        Select users from the table first
                    </p>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Select Action
                    </label>
                    <select id="bulkActionSelect" class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                            style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                        <option value="">Choose an action...</option>
                        <option value="activate">Activate Selected</option>
                        <option value="suspend">Suspend Selected</option>
                        <option value="deactivate">Deactivate Selected</option>
                        @if($canInvite)
                        <option value="send_invitation">Send Invitation</option>
                        @endif
                        @if($canDelete)
                        <option value="delete">Delete Selected</option>
                        @endif
                    </select>
                </div>
                
                <!-- Invitation Options -->
                <div id="bulkInvitationOptions" class="mb-4 hidden">
                    <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                        Expires In (Days)
                    </label>
                    <input type="number" 
                           id="bulkExpiresIn" 
                           value="7" 
                           min="1" 
                           max="30"
                           class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                           style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                </div>
                
                @if(!$developerEmailConfigured)
                <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                    <p class="text-sm" style="color: var(--warning);">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Email configuration is not set up. Cannot send invitations.
                    </p>
                </div>
                @endif
            </div>
            <div class="flex justify-end gap-3 p-5 border-t" style="border-color: var(--border-color);">
                <button type="button" onclick="hideBulkActionsModal()" 
                        class="px-4 py-2 rounded-lg font-medium transition"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                    Cancel
                </button>
                <button type="button" 
                        id="bulkActionBtn"
                        onclick="performBulkAction()"
                        class="px-4 py-2 rounded-lg font-medium text-white transition disabled:opacity-50 disabled:cursor-not-allowed"
                        style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);"
                        disabled>
                    <i class="fas fa-play mr-2"></i> Execute Action
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Invitation Modal -->
<div id="invitationModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70" onclick="hideInvitationModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="rounded-lg shadow-xl w-full max-w-md" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center p-5 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-paper-plane mr-2" style="color: var(--primary);"></i> Send Invitation
                </h3>
                <button type="button" onclick="hideInvitationModal()" class="transition" style="color: var(--text-secondary);">
                    <i class="fas fa-times hover:text-red-500"></i>
                </button>
            </div>
            <form id="invitationForm" method="POST" action="">
                @csrf
                <div class="p-5">
                    <div class="mb-4">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-user-circle mr-2" style="color: var(--primary);"></i>
                            <span style="color: var(--text-primary);" id="invitationUserName"></span>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Expires In (Days)
                        </label>
                        <input type="number" 
                               name="expires_in_days" 
                               value="7" 
                               min="1" 
                               max="30"
                               class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                               style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                            Custom Message (Optional)
                        </label>
                        <textarea name="custom_message" 
                                  rows="3" 
                                  class="w-full px-3 py-2 rounded-lg border focus:outline-none focus:ring-2"
                                  style="background-color: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);"
                                  placeholder="Add a personal message..."></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-3 p-5 border-t" style="border-color: var(--border-color);">
                    <button type="button" onclick="hideInvitationModal()" 
                            class="px-4 py-2 rounded-lg font-medium transition"
                            style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 rounded-lg font-medium text-white transition"
                            style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
                        <i class="fas fa-paper-plane mr-2"></i> Send Invitation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 dark:bg-opacity-70" onclick="hideDeleteModal()"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="rounded-lg shadow-xl w-full max-w-md" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex justify-between items-center p-5 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Confirm Deletion
                </h3>
                <button type="button" onclick="hideDeleteModal()" class="transition" style="color: var(--text-secondary);">
                    <i class="fas fa-times hover:text-red-500"></i>
                </button>
            </div>
            <form id="deleteForm" method="POST" action="">
                @csrf
                @method('DELETE')
                <div class="p-5">
                    <div class="mb-4">
                        <p class="text-sm" id="deleteMessage" style="color: var(--text-secondary);"></p>
                    </div>
                    
                    <div class="mb-4 p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.1); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                        <p class="font-medium text-sm" style="color: var(--danger);">⚠️ Warning:</p>
                        <ul class="text-xs mt-1 space-y-1" style="color: var(--danger);">
                            <li>• Super Admin will be moved to trash</li>
                            <li>• You can restore from trash within 30 days</li>
                            <li>• Permanent deletion requires confirmation from trash</li>
                        </ul>
                    </div>
                </div>
                <div class="flex justify-end gap-3 p-5 border-t" style="border-color: var(--border-color);">
                    <button type="button" onclick="hideDeleteModal()" 
                            class="px-4 py-2 rounded-lg font-medium transition"
                            style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 rounded-lg font-medium text-white transition"
                            style="background-color: var(--danger);">
                        <i class="fas fa-trash-alt mr-2"></i> Move to Trash
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endif

@endsection

@section('scripts')
<script>
// Generate the base URL from the route name prefix
const baseUrl = '/{{ $urlPath }}';

document.addEventListener('DOMContentLoaded', function() {
    @if(!$isReadOnly)
    // Initialize view toggle
    initViewToggle();
    
    // Initialize bulk select
    initBulkSelect();
    
    // Select All checkbox
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkActionButton();
        });
    }
    
    // Auto-hide messages after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.bg-green-100, .bg-red-100').forEach(el => {
            if (el.style.display !== 'none') el.style.display = 'none';
        });
    }, 5000);
    @endif
});

@if(!$isReadOnly)
function initViewToggle() {
    const tableViewBtn = document.getElementById('tableViewBtn');
    const cardViewBtn = document.getElementById('cardViewBtn');
    const tableView = document.getElementById('tableView');
    const cardView = document.getElementById('cardView');
    
    if (!tableViewBtn || !cardViewBtn || !tableView || !cardView) return;
    
    const savedView = localStorage.getItem('superAdminsView') || 'table';
    
    if (savedView === 'card') {
        tableView.classList.add('hidden');
        cardView.classList.remove('hidden');
        tableViewBtn.style.backgroundColor = 'var(--card-bg)';
        tableViewBtn.style.color = 'var(--text-secondary)';
        cardViewBtn.style.backgroundColor = 'var(--primary)';
        cardViewBtn.style.color = 'white';
    } else {
        tableView.classList.remove('hidden');
        cardView.classList.add('hidden');
        tableViewBtn.style.backgroundColor = 'var(--primary)';
        tableViewBtn.style.color = 'white';
        cardViewBtn.style.backgroundColor = 'var(--card-bg)';
        cardViewBtn.style.color = 'var(--text-secondary)';
    }
    
    tableViewBtn.onclick = () => {
        tableView.classList.remove('hidden');
        cardView.classList.add('hidden');
        tableViewBtn.style.backgroundColor = 'var(--primary)';
        tableViewBtn.style.color = 'white';
        cardViewBtn.style.backgroundColor = 'var(--card-bg)';
        cardViewBtn.style.color = 'var(--text-secondary)';
        localStorage.setItem('superAdminsView', 'table');
    };
    
    cardViewBtn.onclick = () => {
        tableView.classList.add('hidden');
        cardView.classList.remove('hidden');
        tableViewBtn.style.backgroundColor = 'var(--card-bg)';
        tableViewBtn.style.color = 'var(--text-secondary)';
        cardViewBtn.style.backgroundColor = 'var(--primary)';
        cardViewBtn.style.color = 'white';
        localStorage.setItem('superAdminsView', 'card');
    };
}

function initBulkSelect() {
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateBulkActionButton);
    });
}

function updateBulkActionButton() {
    const selectedCount = document.querySelectorAll('.user-checkbox:checked').length;
    const selectedText = document.getElementById('selectedCountText');
    const bulkBtn = document.getElementById('bulkActionBtn');
    
    if (selectedText) {
        selectedText.innerHTML = selectedCount > 0 
            ? `<strong style="color: var(--text-primary);">${selectedCount}</strong> user(s) selected`
            : 'Select users from the table first';
    }
    
    if (bulkBtn) {
        if (selectedCount === 0) {
            bulkBtn.disabled = true;
            bulkBtn.classList.add('opacity-50', 'cursor-not-allowed');
        } else {
            bulkBtn.disabled = false;
            bulkBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }
}

function showBulkActionsModal() {
    updateBulkActionButton();
    const modal = document.getElementById('bulkActionsModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function hideBulkActionsModal() {
    const modal = document.getElementById('bulkActionsModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function showInvitationModal(userId, userName) {
    const modal = document.getElementById('invitationModal');
    const form = document.getElementById('invitationForm');
    const userNameSpan = document.getElementById('invitationUserName');
    
    if (!modal || !form) return;
    
    form.action = baseUrl + '/' + userId + '/send-invitation';
    form.reset();
    if (userNameSpan) userNameSpan.innerHTML = `To: ${userName}`;
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideInvitationModal() {
    const modal = document.getElementById('invitationModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function showDeleteModal(userId, userName) {
    const modal = document.getElementById('deleteModal');
    const form = document.getElementById('deleteForm');
    const deleteMessage = document.getElementById('deleteMessage');
    
    if (!modal || !form) return;
    
    const deleteUrl = baseUrl + '/' + userId;
    form.action = deleteUrl;
    
    console.log('Delete URL:', deleteUrl);
    
    if (deleteMessage) {
        deleteMessage.innerHTML = `Are you sure you want to delete Super Admin: <strong>${userName}</strong>?`;
    }
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function hideDeleteModal() {
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function performBulkAction() {
    const action = document.getElementById('bulkActionSelect').value;
    const selectedCheckboxes = document.querySelectorAll('.user-checkbox:checked');
    const userIds = Array.from(selectedCheckboxes).map(cb => cb.value);
    
    if (userIds.length === 0 || !action) {
        showNotification('Please select users and an action.', 'error');
        return;
    }
    
    // Confirmation messages based on action
    let confirmMessage = '';
    let actionConfirmed = false;
    
    switch(action) {
        case 'delete':
            confirmMessage = `Are you sure you want to delete ${userIds.length} Super Admin(s)? They will be moved to trash.`;
            actionConfirmed = confirm(confirmMessage);
            break;
        case 'force_delete':
            confirmMessage = `⚠️ DANGER: Are you ABSOLUTELY sure you want to PERMANENTLY delete ${userIds.length} Super Admin(s)? This action CANNOT be undone!`;
            actionConfirmed = confirm(confirmMessage);
            break;
        case 'activate':
            confirmMessage = `Activate ${userIds.length} Super Admin(s)?`;
            actionConfirmed = confirm(confirmMessage);
            break;
        case 'suspend':
            confirmMessage = `Suspend ${userIds.length} Super Admin(s)?`;
            actionConfirmed = confirm(confirmMessage);
            break;
        case 'deactivate':
            confirmMessage = `Deactivate ${userIds.length} Super Admin(s)?`;
            actionConfirmed = confirm(confirmMessage);
            break;
        case 'send_invitation':
            confirmMessage = `Send invitations to ${userIds.length} Super Admin(s)?`;
            actionConfirmed = confirm(confirmMessage);
            break;
        default:
            actionConfirmed = true;
    }
    
    if (!actionConfirmed) {
        return;
    }
    
    // Show loading state
    const bulkBtn = document.getElementById('bulkActionBtn');
    const originalText = bulkBtn.innerHTML;
    bulkBtn.disabled = true;
    bulkBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
    
    // Prepare form data
    const formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('action', action);
    userIds.forEach(id => {
        formData.append('user_ids[]', id);
    });
    
    if (action === 'send_invitation') {
        const expiresIn = document.getElementById('bulkExpiresIn');
        if (expiresIn && expiresIn.value) {
            formData.append('expires_in_days', expiresIn.value);
        }
    }
    
    // Send AJAX request
    fetch(baseUrl + '/bulk-action', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        // Reset button
        bulkBtn.disabled = false;
        bulkBtn.innerHTML = originalText;
        
        if (data.success) {
            // Show success message
            let message = data.message || `Bulk action completed: ${data.results.success} successful, ${data.results.failed} failed`;
            
            // Show detailed results if there were failures
            if (data.results.failed > 0 && data.results.details && data.results.details.length > 0) {
                console.log('Failed details:', data.results.details);
                message += `. Failed items: ${data.results.details.map(d => d.message).join(', ')}`;
            }
            
            // Show notification
            showNotification(message, 'success');
            
            // Close modal
            hideBulkActionsModal();
            
            // Uncheck all checkboxes
            document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
            updateBulkActionButton();
            
            // Reload page after 1.5 seconds to see changes
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            // Show error message
            showNotification(data.message || 'Bulk action failed', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        bulkBtn.disabled = false;
        bulkBtn.innerHTML = originalText;
        showNotification('An error occurred while processing the bulk action.', 'error');
    });
}

function createInput(name, value) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    return input;
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('Copied to clipboard!', 'success');
    }).catch(() => {
        showNotification('Failed to copy', 'error');
    });
}

function resendInvitation(userId, userName) {
    if (confirm(`Resend invitation to ${userName}?`)) {
        // Show loading state on the button
        const btn = event.target.closest('button');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;
        
        const formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        formData.append('expires_in_days', '7');
        
        fetch(baseUrl + '/' + userId + '/resend-invitation', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
            
            if (data.success) {
                showNotification(data.message || 'Invitation resent successfully!', 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showNotification(data.message || 'Failed to resend invitation', 'error');
            }
        })
        .catch(error => {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
            showNotification('An error occurred while resending the invitation.', 'error');
        });
    }
}

// Show notification function
function showNotification(message, type = 'success') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.custom-notification');
    existingNotifications.forEach(n => n.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `custom-notification fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    } text-white`;
    notification.style.animation = 'slideInRight 0.3s ease-out';
    notification.style.minWidth = '300px';
    notification.style.maxWidth = '500px';
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2 text-lg"></i>
            <span class="text-sm">${escapeHtml(message)}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Remove notification after 4 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 4000);
}

// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Bulk action select change handler
const bulkActionSelect = document.getElementById('bulkActionSelect');
if (bulkActionSelect) {
    bulkActionSelect.addEventListener('change', function() {
        const invitationOptions = document.getElementById('bulkInvitationOptions');
        if (invitationOptions) {
            invitationOptions.classList.toggle('hidden', this.value !== 'send_invitation');
        }
    });
}

// Escape key to close modals
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideBulkActionsModal();
        hideInvitationModal();
        hideDeleteModal();
    }
});

// Add CSS animation for notification
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
    
    .custom-notification {
        z-index: 9999;
        backdrop-filter: blur(8px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
    }
`;
document.head.appendChild(style);
@endif
</script>

<style>
/* Custom styles for the index page */
.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    transition: all 0.2s ease;
    cursor: pointer;
}

.action-btn.view {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
}

.action-btn.view:hover {
    background-color: rgba(var(--info-rgb), 0.2);
    transform: translateY(-2px);
}

.action-btn.edit {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
}

.action-btn.edit:hover {
    background-color: rgba(var(--warning-rgb), 0.2);
    transform: translateY(-2px);
}

.action-btn.invite {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
}

.action-btn.invite:hover {
    background-color: rgba(var(--primary-rgb), 0.2);
    transform: translateY(-2px);
}

.action-btn.delete {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
}

.action-btn.delete:hover {
    background-color: rgba(var(--danger-rgb), 0.2);
    transform: translateY(-2px);
}

.action-btn.resend {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
}

.action-btn.resend:hover {
    background-color: rgba(var(--success-rgb), 0.2);
    transform: translateY(-2px);
}

/* Table styles */
table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}

th {
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 0.7rem;
}

td {
    vertical-align: middle;
}

/* Card view animations */
#cardView .card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

#cardView .card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
}

/* Badge styles */
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

.badge-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--secondary);
}

/* Modal animations */
#bulkActionsModal, #invitationModal, #deleteModal {
    animation: fadeIn 0.2s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* Dark mode adjustments for modals */
@media (prefers-color-scheme: dark) {
    .modal-content {
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    }
}
</style>