@extends('layouts.app')

@section('title', 'Construction Contracts')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-file-contract text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-file-contract mr-2" style="color: var(--primary);"></i> 
                        Construction Contracts
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-tasks mr-2"></i>
                        <span>Manage all construction contracts</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>Admin: {{ auth()->user()->name }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <button onclick="refreshPage()" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-sync-alt mr-2"></i> Refresh
                </button>
            </div>
        </div>
    </div>

    <!-- Navigation Card -->
    <div class="card p-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center space-x-3 flex-wrap gap-2">
                <a href="{{ route('admin.dashboard') }}" 
                   class="inline-flex items-center text-sm font-medium" 
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Dashboard
                </a>
                
                <a href="{{ route('admin.construction.contracts.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-list mr-1"></i> All Contracts
                </a>
                
                <a href="{{ route('admin.workers.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-hard-hat mr-1"></i> Workers
                </a>
                
                <a href="{{ route('properties.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);">
                    <i class="fas fa-building mr-1"></i> Properties
                </a>
                
                <a href="{{ route('admin.construction-registrations.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-hard-hat mr-1"></i> Registrations
                </a>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-8 gap-4 mb-6">
        <!-- Total -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $stats['total'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total</div>
                </div>
            </div>
        </div>
        
        <!-- Pending -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $stats['pending_approval'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Pending</div>
                </div>
            </div>
        </div>
        
        <!-- Approved -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $stats['approved'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Approved</div>
                </div>
            </div>
        </div>
        
        <!-- In Progress -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-hard-hat"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $stats['in_progress'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">In Progress</div>
                </div>
            </div>
        </div>
        
        <!-- Completed -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-flag-checkered"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $stats['completed'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Completed</div>
                </div>
            </div>
        </div>
        
        <!-- On Hold -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-pause-circle"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $stats['on_hold'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">On Hold</div>
                </div>
            </div>
        </div>
        
        <!-- Cancelled -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--danger);">{{ $stats['cancelled'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Cancelled</div>
                </div>
            </div>
        </div>
        
        <!-- Overdue -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--danger);">{{ $stats['overdue'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Overdue</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Success!</strong>
        <span class="block sm:inline">{{ session('success') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Error!</strong>
        <span class="block sm:inline">{{ session('error') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    @if(session('warning'))
    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
        <strong class="font-bold">Warning!</strong>
        <span class="block sm:inline">{{ session('warning') }}</span>
        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    @endif

    <!-- Filters Card -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filters
                </h3>
                @if(request()->hasAny(['status', 'search', 'date_from', 'date_to']))
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-filter mr-1"></i> Filters active
                </div>
                @endif
            </div>
        </div>
        <div class="p-6">
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Status</label>
                        <select name="status" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Statuses</option>
                            @foreach($statuses ?? [] as $status)
                                <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Search</label>
                        <input type="text" 
                               name="search" 
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               placeholder="Contract #, Title, Contractor..."
                               value="{{ request('search') }}">
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Date From</label>
                        <input type="date" 
                               name="date_from" 
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               value="{{ request('date_from') }}">
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Date To</label>
                        <input type="date" 
                               name="date_to" 
                               class="form-input w-full p-3 rounded-lg border"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               value="{{ request('date_to') }}">
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Contractor Type</label>
                        <select name="contractor_type" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Types</option>
                            <option value="individual" {{ request('contractor_type') == 'individual' ? 'selected' : '' }}>Individual</option>
                            <option value="company" {{ request('contractor_type') == 'company' ? 'selected' : '' }}>Company</option>
                        </select>
                    </div>
                </div>
                <div class="flex space-x-2">
                    <button type="submit" 
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                    <a href="{{ route('admin.construction.contracts.index') }}" 
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-times mr-2"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Contracts Table Card -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-list mr-2" style="color: var(--primary);"></i> Contracts
                </h3>
                <div class="text-sm" style="color: var(--text-secondary);">
                    Showing {{ $contracts->firstItem() }} to {{ $contracts->lastItem() }} of {{ $contracts->total() }} contracts
                </div>
            </div>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b" style="border-color: var(--border-color);">
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Contract</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Property</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Contractor</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Amount</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Timeline</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Status</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Invitation</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contracts as $contract)
                            <tr class="border-b transition-colors duration-150" 
                                style="border-color: var(--border-color);"
                                data-contract-id="{{ $contract->id }}"
                                data-contract-number="{{ $contract->contract_number }}"
                                data-status="{{ $contract->status }}"
                                data-contractor-email="{{ $contract->contractor_email }}"
                                data-contractor-phone="{{ $contract->contractor_phone }}"
                                data-contractor-name="{{ $contract->contractor_name }}">
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div class="contract-icon mr-3">
                                            @php
                                                $statusColor = [
                                                    'draft' => 'secondary',
                                                    'pending_approval' => 'warning',
                                                    'approved' => 'success',
                                                    'in_progress' => 'primary',
                                                    'completed' => 'success',
                                                    'cancelled' => 'danger',
                                                    'on_hold' => 'warning',
                                                    'under_review' => 'info'
                                                ][$contract->status] ?? 'secondary';
                                            @endphp
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                                 style="background-color: rgba(var({{ $statusColor }}-rgb), 0.1); color: var(--{{ $statusColor }});">
                                                <i class="fas fa-file-signature"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">
                                                <a href="{{ route('admin.construction.contracts.show', $contract) }}" 
                                                   style="color: var(--primary); text-decoration: none; hover:underline;">
                                                    {{ $contract->contract_number }}
                                                </a>
                                            </div>
                                            <div class="text-sm mt-1" style="color: var(--text-secondary);">
                                                {{ Str::limit($contract->title, 35) }}
                                            </div>
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-calendar-alt mr-1"></i>
                                                Created: {{ $contract->created_at->format('M d, Y') }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    @if($contract->property)
                                        <a href="{{ route('properties.show', $contract->property->id) }}" 
                                           style="color: var(--primary); text-decoration: none; hover:underline;">
                                            {{ Str::limit($contract->property->property_name, 25) }}
                                        </a>
                                        @if($contract->property->digital_address)
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                <i class="fas fa-map-pin mr-1"></i>
                                                {{ $contract->property->digital_address }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-sm" style="color: var(--text-secondary);">Property deleted</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-medium" style="color: var(--text-primary);">
                                        {{ Str::limit($contract->contractor_name, 25) }}
                                    </div>
                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-user-tag mr-1"></i>
                                        {{ ucfirst($contract->contractor_type) }}
                                    </div>
                                    @if($contract->contractor_email)
                                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                            <i class="fas fa-envelope mr-1"></i>
                                            {{ Str::limit($contract->contractor_email, 20) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    @if($contract->contract_amount)
                                        <div class="font-bold" style="color: var(--text-primary);">
                                            ₵{{ number_format($contract->contract_amount, 2) }}
                                        </div>
                                    @else
                                        <span class="text-sm" style="color: var(--text-secondary);">N/A</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <div class="text-sm" style="color: var(--text-primary);">
                                        <i class="fas fa-play text-success mr-1"></i>
                                        {{ $contract->contract_start_date ? $contract->contract_start_date->format('M d, Y') : 'N/A' }}
                                    </div>
                                    <div class="text-sm mt-1" style="color: var(--text-primary);">
                                        <i class="fas fa-flag-checkered text-warning mr-1"></i>
                                        {{ $contract->estimated_completion_date ? $contract->estimated_completion_date->format('M d, Y') : 'N/A' }}
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    @php
                                        $statusColors = [
                                            'draft' => 'secondary',
                                            'pending_approval' => 'warning',
                                            'approved' => 'success',
                                            'in_progress' => 'primary',
                                            'completed' => 'success',
                                            'cancelled' => 'danger',
                                            'on_hold' => 'warning',
                                            'under_review' => 'info'
                                        ];
                                        $statusIcons = [
                                            'draft' => 'fa-file',
                                            'pending_approval' => 'fa-clock',
                                            'approved' => 'fa-check-circle',
                                            'in_progress' => 'fa-hard-hat',
                                            'completed' => 'fa-flag-checkered',
                                            'cancelled' => 'fa-times-circle',
                                            'on_hold' => 'fa-pause-circle',
                                            'under_review' => 'fa-search'
                                        ];
                                        $statusLabels = [
                                            'draft' => 'Draft',
                                            'pending_approval' => 'Pending Approval',
                                            'approved' => 'Approved',
                                            'in_progress' => 'In Progress',
                                            'completed' => 'Completed',
                                            'cancelled' => 'Cancelled',
                                            'on_hold' => 'On Hold',
                                            'under_review' => 'Under Review'
                                        ];
                                        $statusColor = $statusColors[$contract->status] ?? 'secondary';
                                        $statusIcon = $statusIcons[$contract->status] ?? 'fa-question-circle';
                                        $statusLabel = $statusLabels[$contract->status] ?? ucfirst(str_replace('_', ' ', $contract->status));
                                    @endphp
                                    <span class="px-3 py-1 rounded-full text-xs font-medium inline-flex items-center"
                                          style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.1); color: var(--{{ $statusColor }});">
                                        <i class="fas {{ $statusIcon }} mr-1"></i>
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <!-- ✅ FIXED: Enhanced Invitation Status -->
                                    @if($contract->status === 'approved' || $contract->status === 'in_progress')
                                        @php
                                            // Check if contractor has a user account with CONTRACTOR type
                                            $contractorHasAccount = false;
                                            $contractorUser = null;
                                            $contractorStatus = null;
                                            $contractorAccountActive = false;
                                            $contractorInvitationAccepted = false;
                                            $acceptedAt = null;
                                            
                                            // Check by email first
                                            if (!empty($contract->contractor_email)) {
                                                $contractorUser = \App\Models\User::where('email', $contract->contractor_email)
                                                    ->where('type', \App\Models\User::TYPE_CONTRACTOR)
                                                    ->first();
                                            }
                                            
                                            // If not found by email, check by phone
                                            if (!$contractorUser && !empty($contract->contractor_phone)) {
                                                $contractorUser = \App\Models\User::where('phone', $contract->contractor_phone)
                                                    ->where('type', \App\Models\User::TYPE_CONTRACTOR)
                                                    ->first();
                                            }
                                            
                                            if ($contractorUser) {
                                                $contractorHasAccount = true;
                                                $contractorStatus = $contractorUser->status;
                                                $contractorAccountActive = $contractorUser->status === 'active';
                                                
                                                // Check if invitation was accepted (user has password set)
                                                // For contractors, we consider the invitation accepted if:
                                                // 1. The user has a password (not null) AND
                                                // 2. The user has logged in at least once OR
                                                // 3. The user was created via invitation acceptance
                                                $contractorInvitationAccepted = (
                                                    $contractorUser->password !== null && 
                                                    (
                                                        $contractorUser->last_login_at !== null || 
                                                        $contractorUser->email_verified_at !== null ||
                                                        $contractorUser->created_by !== null
                                                    )
                                                );
                                                
                                                // If the user has type CONTRACTOR and is active, that means they've accepted
                                                if ($contractorUser->type === \App\Models\User::TYPE_CONTRACTOR && 
                                                    $contractorUser->status === 'active') {
                                                    $contractorInvitationAccepted = true;
                                                    $acceptedAt = $contractorUser->created_at;
                                                }
                                            }
                                        @endphp
                                        
                                        @if($contractorHasAccount)
                                            <div class="text-xs">
                                                @if($contractorInvitationAccepted && $contractorAccountActive)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                                          style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                        <i class="fas fa-check-circle mr-1"></i>
                                                        Accepted & Activated
                                                        @if($acceptedAt)
                                                            <span class="ml-1" style="color: var(--text-secondary);">
                                                                ({{ $acceptedAt->diffForHumans() }})
                                                            </span>
                                                        @endif
                                                    </span>
                                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                        <i class="fas fa-user-check mr-1" style="color: var(--success);"></i>
                                                        Account: <span style="color: var(--success);">Active</span>
                                                    </div>
                                                @elseif($contractorAccountActive && $contractorUser->password !== null)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                                          style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                        <i class="fas fa-check-circle mr-1"></i>
                                                        Account Active
                                                        @if($contractorUser->created_at)
                                                            <span class="ml-1" style="color: var(--text-secondary);">
                                                                ({{ $contractorUser->created_at->diffForHumans() }})
                                                            </span>
                                                        @endif
                                                    </span>
                                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                        <i class="fas fa-user-check mr-1" style="color: var(--success);"></i>
                                                        Account: <span style="color: var(--success);">Active</span>
                                                    </div>
                                                @elseif($contractorUser->status === 'pending' || $contractorUser->password === null)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                                          style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        Awaiting Acceptance
                                                        @if($contract->contractor_invitation_sent_at)
                                                            <span class="ml-1" style="color: var(--text-secondary);">
                                                                (Sent {{ $contract->contractor_invitation_sent_at->diffForHumans() }})
                                                            </span>
                                                        @endif
                                                    </span>
                                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                        <i class="fas fa-user-clock mr-1" style="color: var(--warning);"></i>
                                                        Account: <span style="color: var(--warning);">Pending Setup</span>
                                                    </div>
                                                    <button type="button" 
                                                            class="text-xs mt-1 text-blue-600 hover:text-blue-800"
                                                            onclick="resendInvitation({{ $contract->id }})">
                                                        <i class="fas fa-paper-plane mr-1"></i> Resend
                                                    </button>
                                                @elseif($contractorUser->status === 'inactive')
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                                          style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                                                        <i class="fas fa-user-slash mr-1"></i>
                                                        Account Inactive
                                                    </span>
                                                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                        <i class="fas fa-exclamation-circle mr-1" style="color: var(--danger);"></i>
                                                        Contractor account is inactive
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            @if($contract->contractor_invitation_sent_at)
                                                <div class="text-xs">
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                                          style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                                        <i class="fas fa-paper-plane mr-1"></i>
                                                        Sent {{ $contract->contractor_invitation_sent_at->diffForHumans() }}
                                                    </span>
                                                    <div class="text-xs mt-1" style="color: var(--warning);">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        Awaiting acceptance
                                                    </div>
                                                    <button type="button" 
                                                            class="text-xs mt-1 text-blue-600 hover:text-blue-800"
                                                            onclick="resendInvitation({{ $contract->id }})">
                                                        <i class="fas fa-paper-plane mr-1"></i> Resend
                                                    </button>
                                                </div>
                                            @else
                                                <span class="text-xs" style="color: var(--text-secondary);">
                                                    <i class="fas fa-hourglass-half mr-1"></i>
                                                    Not sent yet
                                                </span>
                                            @endif
                                        @endif
                                    @else
                                        <span class="text-xs" style="color: var(--text-secondary);">
                                            <i class="fas fa-minus mr-1"></i>
                                            N/A
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex flex-wrap gap-1">
                                        <!-- View Button -->
                                        <a href="{{ route('admin.construction.contracts.show', $contract) }}" 
                                           class="action-btn" 
                                           title="View Details"
                                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <!-- View Workers Button -->
                                        <a href="{{ route('admin.workers.index', ['contract_id' => $contract->id]) }}" 
                                           class="action-btn" 
                                           title="View Workers"
                                           style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                            <i class="fas fa-hard-hat"></i>
                                        </a>
                                        
                                        <!-- Quick Action Buttons based on status -->
                                        @if($contract->status === 'pending_approval')
                                            <button type="button" 
                                                    class="action-btn" 
                                                    title="Approve Contract"
                                                    style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);"
                                                    onclick="openApproveModal(
                                                        {{ $contract->id }}, 
                                                        '{{ $contract->contract_number }}',
                                                        '{{ addslashes($contract->contractor_email) }}',
                                                        '{{ addslashes($contract->contractor_phone) }}',
                                                        '{{ addslashes($contract->contractor_name) }}'
                                                    )">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            
                                            <button type="button" 
                                                    class="action-btn" 
                                                    title="Reject Contract"
                                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                                                    onclick="openRejectModal({{ $contract->id }}, '{{ $contract->contract_number }}')">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        @endif
                                        
                                        @if($contract->status === 'approved')
                                            <button type="button" 
                                                    class="action-btn" 
                                                    title="Start Work"
                                                    style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);"
                                                    onclick="startWork({{ $contract->id }})">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        @endif
                                        
                                        @if($contract->status === 'in_progress')
                                            <button type="button" 
                                                    class="action-btn" 
                                                    title="Pause Work"
                                                    style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);"
                                                    onclick="openPauseModal({{ $contract->id }}, '{{ $contract->contract_number }}')">
                                                <i class="fas fa-pause"></i>
                                            </button>
                                            
                                            <button type="button" 
                                                    class="action-btn" 
                                                    title="Complete Contract"
                                                    style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);"
                                                    onclick="openCompleteModal({{ $contract->id }}, '{{ $contract->contract_number }}')">
                                                <i class="fas fa-flag-checkered"></i>
                                            </button>
                                        @endif
                                        
                                        @if($contract->status === 'on_hold')
                                            <button type="button" 
                                                    class="action-btn" 
                                                    title="Resume Work"
                                                    style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);"
                                                    onclick="resumeWork({{ $contract->id }})">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-8 px-4 text-center" style="color: var(--text-secondary);">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="fas fa-file-contract text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                                        <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">No contracts found</p>
                                        <p style="color: var(--text-secondary);">Try adjusting your filters</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($contracts->hasPages())
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    {{ $contracts->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- APPROVE MODAL WITH INVITATION OPTIONS -->
<!-- ============================================ -->
<div id="approveModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('approveModal')"></div>
    <div class="modal-container" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-check-circle text-success mr-2"></i>
                Approve Contract & Send Invitation
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('approveModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="approveForm" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PATCH')
            <div class="modal-body">
                <div class="space-y-4">
                    <!-- Contract Details -->
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">Contract Details</p>
                        <p class="text-sm" id="approveContractNumber" style="color: var(--text-secondary);"></p>
                        <p class="text-sm" id="approveContractorName" style="color: var(--text-secondary);"></p>
                    </div>
                    
                    <!-- Admin Notes -->
                    <div>
                        <label for="admin_notes" class="block mb-2 font-medium" style="color: var(--text-primary);">Admin Notes (Optional)</label>
                        <textarea name="admin_notes" id="admin_notes" rows="2"
                                  class="form-input w-full p-3 rounded-lg border"
                                  style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                  placeholder="Add any notes about this approval..."></textarea>
                    </div>
                    
                    <!-- Invitation Section -->
                    <div class="border-t pt-4" style="border-color: var(--border-color);">
                        <h4 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">
                            <i class="fas fa-paper-plane text-primary mr-2"></i>
                            Contractor Invitation
                        </h4>
                        
                        <div class="space-y-3">
                            <!-- Send Invitation Toggle -->
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       name="send_invitation_to_contractor" 
                                       id="send_invitation" 
                                       value="1" 
                                       checked
                                       class="mr-2"
                                       style="min-width: 18px; min-height: 18px; cursor: pointer;">
                                <label for="send_invitation" class="text-sm font-medium" style="color: var(--text-primary); cursor: pointer;">
                                    Send invitation to contractor
                                </label>
                            </div>
                            
                            <!-- Invitation Channels -->
                            <div id="invitationOptions">
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Invitation Channels
                                </label>
                                <div class="flex flex-wrap gap-4">
                                    <label class="flex items-center">
                                        <input type="checkbox" 
                                               name="invitation_channels[]" 
                                               value="email" 
                                               checked
                                               class="mr-2"
                                               style="min-width: 16px; min-height: 16px;">
                                        <i class="fas fa-envelope mr-1" style="color: var(--primary);"></i>
                                        Email
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" 
                                               name="invitation_channels[]" 
                                               value="sms"
                                               class="mr-2"
                                               style="min-width: 16px; min-height: 16px;">
                                        <i class="fas fa-phone mr-1" style="color: var(--success);"></i>
                                        SMS
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" 
                                               name="invitation_channels[]" 
                                               value="whatsapp"
                                               class="mr-2"
                                               style="min-width: 16px; min-height: 16px;">
                                        <i class="fab fa-whatsapp mr-1" style="color: #25D366;"></i>
                                        WhatsApp
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Invitation Type -->
                            <div>
                                <label class="block text-sm font-medium mb-2" style="color: var(--text-primary);">
                                    Invitation Type
                                </label>
                                <select name="invitation_type" 
                                        class="form-input w-full p-3 rounded-lg border"
                                        style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                                    <option value="welcome">Welcome</option>
                                    <option value="registration">Registration</option>
                                    <option value="account_setup">Account Setup</option>
                                    <option value="password_setup">Password Setup</option>
                                </select>
                            </div>
                            
                            <!-- Contractor Contact (Editable - Pre-filled from contract) -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                        Contractor Email <span style="color: var(--danger);">*</span>
                                    </label>
                                    <input type="email" 
                                           name="contractor_email" 
                                           id="modal_contractor_email"
                                           class="form-input w-full p-2 rounded-lg border text-sm"
                                           style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                           placeholder="contractor@example.com"
                                           required>
                                    <small class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Pre-filled from contract submission
                                    </small>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                        Contractor Phone <span style="color: var(--danger);">*</span>
                                    </label>
                                    <input type="text" 
                                           name="contractor_phone" 
                                           id="modal_contractor_phone"
                                           class="form-input w-full p-2 rounded-lg border text-sm"
                                           style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                           placeholder="0244123456"
                                           required>
                                    <small class="text-xs mt-1" style="color: var(--text-secondary);">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Pre-filled from contract submission
                                    </small>
                                </div>
                            </div>
                            
                            <!-- Custom Message Preview -->
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary);">
                                    <i class="fas fa-file-alt mr-1"></i>
                                    Invitation Message Preview
                                </label>
                                <div class="p-3 rounded-lg text-sm" 
                                     style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid var(--border-color); color: var(--text-secondary); max-height: 150px; overflow-y: auto;">
                                    <p id="invitationMessagePreview">
                                        Loading message preview...
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('approveModal')">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background-color: var(--success);">
                    <i class="fas fa-check mr-2"></i> Approve & Invite
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- REJECT MODAL -->
<!-- ============================================ -->
<div id="rejectModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('rejectModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-times-circle text-danger mr-2"></i>
                Reject Contract
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('rejectModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="rejectForm" method="POST">
            @csrf
            @method('PATCH')
            <div class="modal-body">
                <div class="space-y-4">
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">Contract Details</p>
                        <p class="text-sm" id="rejectContractNumber" style="color: var(--text-secondary);"></p>
                    </div>
                    
                    <div>
                        <label for="rejection_reason" class="block mb-2 font-medium required" style="color: var(--text-primary);">Rejection Reason</label>
                        <textarea name="rejection_reason" id="rejection_reason" rows="3" required
                                  class="form-input w-full p-3 rounded-lg border"
                                  style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                  placeholder="Please provide a clear reason for rejection..."></textarea>
                        <small class="text-xs mt-1" style="color: var(--text-secondary);">This reason will be visible to the landlord.</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('rejectModal')">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background-color: var(--danger);">
                    <i class="fas fa-times mr-2"></i> Reject Contract
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- PAUSE MODAL -->
<!-- ============================================ -->
<div id="pauseModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('pauseModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-pause-circle text-warning mr-2"></i>
                Pause Contract
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('pauseModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="pauseForm" method="POST">
            @csrf
            @method('PATCH')
            <div class="modal-body">
                <div class="space-y-4">
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">Contract Details</p>
                        <p class="text-sm" id="pauseContractNumber" style="color: var(--text-secondary);"></p>
                    </div>
                    
                    <div>
                        <label for="pause_reason" class="block mb-2 font-medium required" style="color: var(--text-primary);">Pause Reason</label>
                        <textarea name="pause_reason" id="pause_reason" rows="3" required
                                  class="form-input w-full p-3 rounded-lg border"
                                  style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                  placeholder="Please provide a reason for pausing..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('pauseModal')">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background-color: var(--warning);">
                    <i class="fas fa-pause mr-2"></i> Pause Contract
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- COMPLETE MODAL -->
<!-- ============================================ -->
<div id="completeModal" class="modal hidden">
    <div class="modal-overlay" onclick="closeModal('completeModal')"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-flag-checkered text-success mr-2"></i>
                Complete Contract
            </h3>
            <button type="button" class="modal-close" onclick="closeModal('completeModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="completeForm" method="POST">
            @csrf
            @method('PATCH')
            <div class="modal-body">
                <div class="space-y-4">
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05); border: 1px solid rgba(var(--primary-rgb), 0.1);">
                        <p class="text-sm font-medium mb-1" style="color: var(--text-primary);">Contract Details</p>
                        <p class="text-sm" id="completeContractNumber" style="color: var(--text-secondary);"></p>
                    </div>
                    
                    <div>
                        <label for="completion_notes" class="block mb-2 font-medium" style="color: var(--text-primary);">Completion Notes (Optional)</label>
                        <textarea name="completion_notes" id="completion_notes" rows="3"
                                  class="form-input w-full p-3 rounded-lg border"
                                  style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                  placeholder="Add any notes about the completion..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('completeModal')">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background-color: var(--success);">
                    <i class="fas fa-flag-checkered mr-2"></i> Complete Contract
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay hidden">
    <div class="text-center">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary mb-4"></div>
        <div class="text-white font-medium" id="loadingMessage">Processing...</div>
    </div>
</div>

@endsection

@section('scripts')
<script>
// ============================================
// MODAL FUNCTIONS
// ============================================

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

// ============================================
// APPROVE MODAL WITH PRE-FILLED CONTRACTOR DATA
// ============================================
function openApproveModal(contractId, contractNumber, contractorEmail, contractorPhone, contractorName) {
    const form = document.getElementById('approveForm');
    form.action = `/admin/construction/contracts/${contractId}/approve`;
    
    // Set contract details
    document.getElementById('approveContractNumber').textContent = `Contract #: ${contractNumber}`;
    document.getElementById('approveContractorName').textContent = `Contractor: ${contractorName || 'N/A'}`;
    document.getElementById('admin_notes').value = '';
    
    // Pre-fill contractor email and phone from the contract
    const emailInput = document.getElementById('modal_contractor_email');
    const phoneInput = document.getElementById('modal_contractor_phone');
    
    emailInput.value = contractorEmail || '';
    phoneInput.value = contractorPhone || '';
    
    // Show message preview with contractor details
    const preview = document.getElementById('invitationMessagePreview');
    preview.innerHTML = `
        <p><strong>To:</strong> ${contractorName || 'Contractor'}</p>
        <p><strong>Email:</strong> ${contractorEmail || 'Not provided'}</p>
        <p><strong>Phone:</strong> ${contractorPhone || 'Not provided'}</p>
        <p><strong>Contract:</strong> ${contractNumber}</p>
        <p class="mt-2">You have been invited to join the system as a contractor for this project.</p>
        <p>Please click the link in your invitation to set up your account.</p>
    `;
    
    openModal('approveModal');
}

// ============================================
// TOGGLE INVITATION OPTIONS
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const sendInvitationCheckbox = document.getElementById('send_invitation');
    const invitationOptions = document.getElementById('invitationOptions');
    
    if (sendInvitationCheckbox && invitationOptions) {
        sendInvitationCheckbox.addEventListener('change', function() {
            if (this.checked) {
                invitationOptions.style.display = 'block';
                invitationOptions.style.opacity = '1';
            } else {
                invitationOptions.style.display = 'none';
                invitationOptions.style.opacity = '0.5';
            }
        });
    }
});

// ============================================
// RESEND INVITATION
// ============================================
function resendInvitation(contractId) {
    if (!confirm('Are you sure you want to resend the invitation to the contractor?')) {
        return;
    }
    
    showLoading('Resending invitation...');
    
    const formData = new FormData();
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
    
    fetch(`/admin/construction/contracts/${contractId}/resend-invitation`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showToast('Invitation resent successfully!', 'success');
            setTimeout(refreshPage, 2000);
        } else {
            showToast(data.message || 'Failed to resend invitation', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        console.error('Error resending invitation:', error);
        showToast('Failed to resend invitation. Please try again.', 'error');
    });
}

// ============================================
// REJECT MODAL
// ============================================
function openRejectModal(contractId, contractNumber) {
    const form = document.getElementById('rejectForm');
    form.action = `/admin/construction/contracts/${contractId}/reject`;
    document.getElementById('rejectContractNumber').textContent = `Contract #: ${contractNumber}`;
    document.getElementById('rejection_reason').value = '';
    openModal('rejectModal');
}

// ============================================
// PAUSE MODAL
// ============================================
function openPauseModal(contractId, contractNumber) {
    const form = document.getElementById('pauseForm');
    form.action = `/admin/construction/contracts/${contractId}/pause`;
    document.getElementById('pauseContractNumber').textContent = `Contract #: ${contractNumber}`;
    document.getElementById('pause_reason').value = '';
    openModal('pauseModal');
}

// ============================================
// COMPLETE MODAL
// ============================================
function openCompleteModal(contractId, contractNumber) {
    const form = document.getElementById('completeForm');
    form.action = `/admin/construction/contracts/${contractId}/complete`;
    document.getElementById('completeContractNumber').textContent = `Contract #: ${contractNumber}`;
    document.getElementById('completion_notes').value = '';
    openModal('completeModal');
}

// ============================================
// START WORK
// ============================================
function startWork(contractId) {
    if (confirm('Are you sure you want to start work on this contract?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/admin/construction/contracts/${contractId}/start`;
        
        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = document.querySelector('meta[name="csrf-token"]').content;
        form.appendChild(csrf);
        
        const method = document.createElement('input');
        method.type = 'hidden';
        method.name = '_method';
        method.value = 'PATCH';
        form.appendChild(method);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// ============================================
// RESUME WORK
// ============================================
function resumeWork(contractId) {
    if (confirm('Are you sure you want to resume work on this contract?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/admin/construction/contracts/${contractId}/resume`;
        
        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = document.querySelector('meta[name="csrf-token"]').content;
        form.appendChild(csrf);
        
        const method = document.createElement('input');
        method.type = 'hidden';
        method.name = '_method';
        method.value = 'PATCH';
        form.appendChild(method);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// ============================================
// REFRESH PAGE
// ============================================
function refreshPage() {
    window.location.reload();
}

// ============================================
// TOAST SYSTEM
// ============================================
function showToast(message, type = 'info') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        type === 'warning' ? 'bg-yellow-100 text-yellow-800 border border-yellow-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;
    
    const iconMap = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    toast.innerHTML = `
        <i class="fas ${iconMap[type] || 'fa-info-circle'} mr-2"></i>
        <span class="text-sm font-medium flex-1">${message}</span>
        <button class="ml-4 transition-colors duration-200 hover:opacity-70" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);
    
    setTimeout(() => {
        if (toast.parentNode === toastContainer) {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

// ============================================
// LOADING OVERLAY
// ============================================
function showLoading(message = 'Processing...') {
    const overlay = document.getElementById('loadingOverlay');
    const loadingMessage = document.getElementById('loadingMessage');
    if (overlay && loadingMessage) {
        loadingMessage.textContent = message;
        overlay.classList.remove('hidden');
    }
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.add('hidden');
    }
}

// ============================================
// CLOSE MODALS ON OVERLAY CLICK
// ============================================
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        const modal = e.target.closest('.modal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    }
});

// ============================================
// CLOSE MODALS WITH ESCAPE KEY
// ============================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(modal => {
            if (!modal.classList.contains('hidden')) {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        });
    }
});

// ============================================
// AUTO-HIDE ALERTS
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.bg-green-100, .bg-red-100, .bg-yellow-100');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 500);
        }, 5000);
    });
});

console.log('🚀 Admin Construction Contracts Index with Workers Management Loaded');
</script>

<style>
/* ============================================ */
/* CARD STYLES */
/* ============================================ */
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

/* ============================================ */
/* BUTTON STYLES */
/* ============================================ */
.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
}

/* ============================================ */
/* FORM ELEMENTS */
/* ============================================ */
.form-input {
    background-color: var(--card-bg) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px !important;
    transition: all 0.2s ease !important;
}

.form-input:focus {
    outline: none !important;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1) !important;
}

/* ============================================ */
/* ACTION BUTTONS */
/* ============================================ */
.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* ============================================ */
/* MODAL STYLES */
/* ============================================ */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
}

.modal.hidden {
    display: none;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
}

.modal-container {
    position: relative;
    background-color: var(--card-bg);
    border-radius: 16px;
    margin: 2rem auto;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    border: 1px solid var(--border-color);
    animation: modalFadeIn 0.3s ease-out;
}

.modal-header {
    padding: 1.5rem 1.5rem 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 1.25rem;
    padding: 0.25rem;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--text-primary);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    background-color: var(--bg-secondary);
    border-radius: 0 0 16px 16px;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* ============================================ */
/* LOADING OVERLAY */
/* ============================================ */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.loading-overlay.hidden {
    display: none;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.animate-spin {
    animation: spin 1s linear infinite;
    border-top-color: transparent;
}

/* ============================================ */
/* TABLE STYLES */
/* ============================================ */
table th, table td {
    padding: 0.75rem 1rem;
}

tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.02) !important;
}

/* ============================================ */
/* CONTRACT ICON */
/* ============================================ */
.contract-icon {
    transition: all 0.2s ease;
}

tr:hover .contract-icon {
    transform: scale(1.1);
}

/* ============================================ */
/* REQUIRED FIELD INDICATOR */
/* ============================================ */
.required::after {
    content: '*';
    color: var(--danger);
    margin-left: 4px;
}

/* ============================================ */
/* SCROLLBAR STYLING */
/* ============================================ */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

::-webkit-scrollbar-track {
    background: rgba(var(--primary-rgb), 0.05);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: rgba(var(--primary-rgb), 0.2);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(var(--primary-rgb), 0.3);
}

/* ============================================ */
/* RESPONSIVE ADJUSTMENTS */
/* ============================================ */
@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-8 {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .grid.grid-cols-1.md\:grid-cols-5 {
        grid-template-columns: 1fr;
    }
    
    table th, table td {
        padding: 0.5rem;
        font-size: 0.875rem;
    }
    
    .modal-container {
        margin: 1rem;
    }
}

@media (max-width: 640px) {
    .grid.grid-cols-1.md\:grid-cols-8 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .p-6 {
        padding: 1rem !important;
    }
    
    .text-xl {
        font-size: 1.25rem !important;
    }
    
    .text-2xl {
        font-size: 1.5rem !important;
    }
}
</style>
@endsection