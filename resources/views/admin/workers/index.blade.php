@extends('layouts.app')

@section('title', 'Worker Management')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-hard-hat text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-hard-hat mr-2" style="color: var(--primary);"></i> 
                        Site Workers
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-users mr-2"></i>
                        <span>Track all workers assigned to sites across all contractors</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-user-shield mr-2"></i>
                        <span>Admin: {{ auth()->user()->name }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <a href="{{ route('admin.workers.export', request()->query()) }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-file-export mr-2"></i> Export
                </a>
                <a href="{{ route('admin.workers.site-report') }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-clipboard-list mr-2"></i> Site Report
                </a>
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
                    <i class="fas fa-file-contract mr-1"></i> Contracts
                </a>
                
                <a href="{{ route('admin.workers.index') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-hard-hat mr-1"></i> Site Workers
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
        <!-- Total Site Workers -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-hard-hat"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--primary);">{{ $stats['total'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Total on Site</div>
                </div>
            </div>
        </div>
        
        <!-- Active -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-play-circle"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $stats['active'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Active</div>
                </div>
            </div>
        </div>
        
        <!-- Inactive -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-pause-circle"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--warning);">{{ $stats['inactive'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Inactive</div>
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
        
        <!-- Terminated -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--danger);">{{ $stats['terminated'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Terminated</div>
                </div>
            </div>
        </div>
        
        <!-- Has Badge -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-id-card"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--info);">{{ $stats['badge_sent'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Badges Sent</div>
                </div>
            </div>
        </div>
        
        <!-- No Badge -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);">
                        <i class="fas fa-id-card"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--secondary);">{{ $stats['no_badge'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">No Badge</div>
                </div>
            </div>
        </div>
        
        <!-- Active Badges -->
        <div class="card p-4">
            <div class="flex items-center">
                <div class="mr-3">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div>
                    <div class="text-2xl font-bold" style="color: var(--success);">{{ $stats['badge_active'] ?? 0 }}</div>
                    <div class="text-sm mt-1" style="color: var(--text-secondary);">Active Badges</div>
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
                @if(request()->hasAny(['contractor_id', 'contract_id', 'status', 'trade', 'badge_status', 'search']))
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-filter mr-1"></i> Filters active
                </div>
                @endif
            </div>
        </div>
        <div class="p-6">
            <form method="GET" action="{{ route('admin.workers.index') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Contractor</label>
                        <select name="contractor_id" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Contractors</option>
                            @foreach($contractors ?? [] as $contractor)
                                <option value="{{ $contractor->id }}" {{ request('contractor_id') == $contractor->id ? 'selected' : '' }}>
                                    {{ $contractor->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Status</label>
                        <select name="status" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Statuses</option>
                            @foreach(['active', 'inactive', 'completed', 'terminated'] as $status)
                                <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                                    {{ ucfirst($status) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Trade</label>
                        <select name="trade" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Trades</option>
                            @foreach($trades ?? [] as $trade)
                                <option value="{{ $trade }}" {{ request('trade') == $trade ? 'selected' : '' }}>
                                    {{ ucfirst($trade) }}
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
                               placeholder="Name, email, phone..."
                               value="{{ request('search') }}">
                    </div>
                </div>
                
                <!-- Badge Status Filter Row -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Badge Status</label>
                        <select name="badge_status" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Badge Statuses</option>
                            <option value="has_badge" {{ request('badge_status') == 'has_badge' ? 'selected' : '' }}>Has Badge</option>
                            <option value="no_badge" {{ request('badge_status') == 'no_badge' ? 'selected' : '' }}>No Badge</option>
                            <option value="badge_sent" {{ request('badge_status') == 'badge_sent' ? 'selected' : '' }}>Badge Sent</option>
                            <option value="badge_active" {{ request('badge_status') == 'badge_active' ? 'selected' : '' }}>Active Badge</option>
                            <option value="badge_expired" {{ request('badge_status') == 'badge_expired' ? 'selected' : '' }}>Badge Expired</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block mb-2 font-medium" style="color: var(--text-primary);">Contract</label>
                        <select name="contract_id" 
                                class="form-input w-full p-3 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="">All Contracts</option>
                            @foreach($contracts ?? [] as $contract)
                                <option value="{{ $contract->id }}" {{ request('contract_id') == $contract->id ? 'selected' : '' }}>
                                    {{ $contract->contract_number }}
                                </option>
                            @endforeach
                        </select>
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
                </div>
                
                <div class="flex space-x-2">
                    <button type="submit" 
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                    <a href="{{ route('admin.workers.index') }}" 
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-times mr-2"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Workers Table Card -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-list mr-2" style="color: var(--primary);"></i> Site Workers
                </h3>
                @if($workers->count() > 0)
                <div class="text-sm" style="color: var(--text-secondary);">
                    Showing {{ $workers->firstItem() }} to {{ $workers->lastItem() }} of {{ $workers->total() }} site workers
                </div>
                @endif
            </div>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b" style="border-color: var(--border-color);">
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Worker</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Contractor</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Contract</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Site</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Trade</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Status</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Badge</th>
                            <th class="text-left py-3 px-4 font-medium" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($workers as $worker)
                            <tr class="border-b transition-colors duration-150" 
                                style="border-color: var(--border-color);"
                                data-worker-id="{{ $worker->id }}"
                                data-worker-name="{{ $worker->full_name }}">
                                <td class="py-3 px-4">
                                    <div class="flex items-center">
                                        <div class="worker-icon mr-3">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                                 style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">
                                                {{ $worker->full_name }}
                                            </div>
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                @if($worker->email)
                                                    <span class="mr-2"><i class="fas fa-envelope mr-1"></i>{{ Str::limit($worker->email, 20) }}</span>
                                                @endif
                                                @if($worker->phone)
                                                    <span><i class="fas fa-phone mr-1"></i>{{ $worker->phone }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    @if($worker->contract && $worker->contract->contractor)
                                        <div class="font-medium" style="color: var(--text-primary);">
                                            {{ $worker->contract->contractor->name }}
                                        </div>
                                        @if($worker->contract->contractor->company_name)
                                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                {{ $worker->contract->contractor->company_name }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-sm" style="color: var(--text-secondary);">N/A</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    @if($worker->contract)
                                        <a href="{{ route('admin.construction.contracts.show', $worker->contract->id) }}" 
                                           style="color: var(--primary); text-decoration: none; hover:underline;">
                                            {{ $worker->contract->contract_number }}
                                        </a>
                                    @else
                                        <span class="text-sm" style="color: var(--text-secondary);">N/A</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    @if($worker->contract)
                                        <div class="flex items-center">
                                            <i class="fas fa-map-marker-alt mr-1" style="color: var(--primary);"></i>
                                            <span class="text-sm" style="color: var(--text-primary);">
                                                {{ $worker->contract->location ?? 'Not specified' }}
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-sm" style="color: var(--text-secondary);">N/A</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    @if($worker->trade)
                                        <span class="px-2 py-1 rounded-full text-xs font-medium inline-flex items-center"
                                              style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                            <i class="fas fa-tools mr-1"></i>
                                            {{ ucfirst($worker->trade) }}
                                        </span>
                                    @else
                                        <span class="text-sm" style="color: var(--text-secondary);">N/A</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    @php
                                        $statusColors = [
                                            'active' => 'success',
                                            'inactive' => 'warning',
                                            'completed' => 'success',
                                            'terminated' => 'danger',
                                            'pending' => 'warning'
                                        ];
                                        $statusIcons = [
                                            'active' => 'fa-play-circle',
                                            'inactive' => 'fa-pause-circle',
                                            'completed' => 'fa-flag-checkered',
                                            'terminated' => 'fa-times-circle',
                                            'pending' => 'fa-clock'
                                        ];
                                        $statusColor = $statusColors[$worker->status] ?? 'secondary';
                                        $statusIcon = $statusIcons[$worker->status] ?? 'fa-question-circle';
                                        $statusLabel = ucfirst($worker->status ?? 'Unknown');
                                    @endphp
                                    <span class="px-3 py-1 rounded-full text-xs font-medium inline-flex items-center"
                                          style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.1); color: var(--{{ $statusColor }});">
                                        <i class="fas {{ $statusIcon }} mr-1"></i>
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    @if($worker->badge)
                                        @php
                                            $badgeColors = [
                                                'active' => 'success',
                                                'expired' => 'danger',
                                                'inactive' => 'secondary',
                                                'pending' => 'warning'
                                            ];
                                            $badgeColor = $badgeColors[$worker->badge->status] ?? 'secondary';
                                        @endphp
                                        <div>
                                            <span class="px-2 py-1 rounded-full text-xs font-medium inline-flex items-center"
                                                  style="background-color: rgba(var(--{{ $badgeColor }}-rgb), 0.1); color: var(--{{ $badgeColor }});">
                                                <i class="fas fa-id-card mr-1"></i>
                                                {{ $worker->badge->badge_number }}
                                            </span>
                                            @if($worker->badge->valid_until)
                                                <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                                    <i class="fas fa-calendar-alt mr-1"></i>
                                                    Exp: {{ $worker->badge->valid_until->format('M d, Y') }}
                                                    @if($worker->badge->is_expired)
                                                        <span style="color: var(--danger);">(Expired)</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-sm" style="color: var(--text-secondary);">No badge</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex flex-wrap gap-1">
                                        <!-- View Button - Opens Modal -->
                                        <button type="button" 
                                                class="action-btn" 
                                                title="View Details"
                                                style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                                onclick="openViewWorkerModal({{ $worker->id }})">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <!-- View Contract -->
                                        @if($worker->contract)
                                            <a href="{{ route('admin.construction.contracts.show', $worker->contract->id) }}" 
                                               class="action-btn" 
                                               title="View Contract"
                                               style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                                <i class="fas fa-file-contract"></i>
                                            </a>
                                        @endif
                                        
                                        <!-- ✅ NEW: Site Assignment Badge -->
                                        @if($worker->is_assigned_to_site)
                                            <span class="px-2 py-1 rounded-full text-xs font-medium inline-flex items-center"
                                                  style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                                <i class="fas fa-check-circle mr-1"></i> On Site
                                            </span>
                                        @endif
                                        
                                        <!-- Revoke Badge (Admin only) -->
                                        @if($worker->badge && $worker->badge->is_active)
                                            <button type="button" 
                                                    class="action-btn" 
                                                    title="Revoke Badge"
                                                    style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                                                    onclick="revokeBadge({{ $worker->id }})">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-8 px-4 text-center" style="color: var(--text-secondary);">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="fas fa-hard-hat text-4xl mb-3" style="color: var(--text-secondary); opacity: 0.5;"></i>
                                        <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">No site workers found</p>
                                        <p style="color: var(--text-secondary);">Workers must be assigned to a site by the contractor</p>
                                        @if(request()->hasAny(['contractor_id', 'status', 'trade', 'badge_status', 'search']))
                                            <a href="{{ route('admin.workers.index') }}" class="btn btn-primary mt-4">
                                                <i class="fas fa-times mr-2"></i> Clear Filters
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($workers->hasPages())
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    {{ $workers->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- VIEW WORKER DETAILS MODAL (ADMIN VERSION) -->
<!-- ============================================ -->
<div id="viewWorkerModal" class="custom-modal">
    <div class="custom-modal-overlay" onclick="closeViewWorkerModal()"></div>
    <div class="custom-modal-dialog custom-modal-lg">
        <div class="custom-modal-content theme-aware">
            <div class="custom-modal-header">
                <h5 class="custom-modal-title">
                    <i class="fas fa-user mr-2"></i> Site Worker Details
                </h5>
                <button type="button" class="custom-modal-close" onclick="closeViewWorkerModal()" aria-label="Close">
                    &times;
                </button>
            </div>
            <div class="custom-modal-body">
                <!-- Loading State -->
                <div id="viewWorkerLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading worker details...</p>
                </div>
                
                <!-- Worker Details Content -->
                <div id="viewWorkerContent" style="display: none;">
                    <div class="worker-profile">
                        <!-- Header with Name and Status -->
                        <div class="worker-profile-header">
                            <div class="worker-avatar">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div class="worker-header-info">
                                <h3 id="viewWorkerName" class="worker-name-display">-</h3>
                                <div class="worker-header-meta">
                                    <span id="viewWorkerStatus" class="status-badge">-</span>
                                    <span id="viewWorkerTrade" class="trade-badge">-</span>
                                    <span id="viewWorkerBadgeStatus" class="status-badge" style="display: none;">-</span>
                                    <span id="viewWorkerSiteStatus" class="status-badge" style="display: none;">-</span>
                                </div>
                                <div class="worker-header-meta" style="margin-top: 0.25rem;">
                                    <span class="meta-tag">
                                        <i class="fas fa-building"></i> 
                                        Contractor: <strong id="viewContractorName">-</strong>
                                    </span>
                                    <span class="meta-tag">
                                        <i class="fas fa-file-contract"></i> 
                                        Contract: <strong id="viewContractNumber">-</strong>
                                    </span>
                                    <span class="meta-tag">
                                        <i class="fas fa-map-marker-alt"></i> 
                                        Site: <strong id="viewSiteName">-</strong>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Admin Overview Cards -->
                        <div class="admin-overview-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap:1rem; margin-bottom:1.5rem;">
                            <div class="overview-card">
                                <div class="overview-label">Site Status</div>
                                <div class="overview-value" id="viewSiteStatus">-</div>
                            </div>
                            <div class="overview-card">
                                <div class="overview-label">Badge Verifications</div>
                                <div class="overview-value" id="viewVerificationCount">-</div>
                            </div>
                            <div class="overview-card">
                                <div class="overview-label">Added By</div>
                                <div class="overview-value" id="viewAddedByName">-</div>
                            </div>
                            <div class="overview-card">
                                <div class="overview-label">Days Until Expiry</div>
                                <div class="overview-value" id="viewDaysUntilExpiry">-</div>
                            </div>
                        </div>

                        <!-- Details Grid -->
                        <div class="worker-details-grid">
                            <!-- Personal Information -->
                            <div class="detail-section">
                                <h6 class="section-title">
                                    <i class="fas fa-id-card mr-2"></i> Personal Information
                                </h6>
                                <div class="detail-row">
                                    <span class="detail-label">Full Name</span>
                                    <span id="viewFullName" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Email</span>
                                    <span id="viewEmail" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Phone</span>
                                    <span id="viewPhone" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">ID Number</span>
                                    <span id="viewIdNumber" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Address</span>
                                    <span id="viewAddress" class="detail-value">-</span>
                                </div>
                            </div>

                            <!-- Employment Details -->
                            <div class="detail-section">
                                <h6 class="section-title">
                                    <i class="fas fa-briefcase mr-2"></i> Employment Details
                                </h6>
                                <div class="detail-row">
                                    <span class="detail-label">Job Title</span>
                                    <span id="viewJobTitle" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Trade</span>
                                    <span id="viewTrade" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Specialization</span>
                                    <span id="viewSpecialization" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Daily Rate</span>
                                    <span id="viewDailyRate" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Status</span>
                                    <span id="viewStatus" class="detail-value">-</span>
                                </div>
                            </div>

                            <!-- Contractor & Contract Details -->
                            <div class="detail-section">
                                <h6 class="section-title">
                                    <i class="fas fa-building mr-2"></i> Contractor & Contract
                                </h6>
                                <div class="detail-row">
                                    <span class="detail-label">Contractor</span>
                                    <span id="viewContractorFull" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Contract</span>
                                    <span id="viewContractFull" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Site Location</span>
                                    <span id="viewSiteLocation" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Contract Status</span>
                                    <span id="viewContractStatus" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Added On</span>
                                    <span id="viewAddedOn" class="detail-value">-</span>
                                </div>
                            </div>

                            <!-- Badge Details -->
                            <div class="detail-section" id="viewBadgeSection" style="display: none;">
                                <h6 class="section-title">
                                    <i class="fas fa-id-card mr-2"></i> Badge Details
                                </h6>
                                <div class="detail-row">
                                    <span class="detail-label">Badge Number</span>
                                    <span id="viewBadgeNumber" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Badge Status</span>
                                    <span id="viewBadgeStatus" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Valid From</span>
                                    <span id="viewBadgeValidFrom" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Valid Until</span>
                                    <span id="viewBadgeValidUntil" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Email Sent</span>
                                    <span id="viewBadgeEmailSent" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Verification URL</span>
                                    <span id="viewBadgeVerificationUrl" class="detail-value">
                                        <a href="#" target="_blank" class="text-primary">View Badge</a>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Skills Section -->
                        <div id="viewSkillsSection" class="detail-section" style="display: none;">
                            <h6 class="section-title">
                                <i class="fas fa-tools mr-2"></i> Skills
                            </h6>
                            <div id="viewSkills" class="skills-container"></div>
                        </div>

                        <!-- Description & Notes -->
                        <div id="viewDescriptionSection" class="detail-section" style="display: none;">
                            <h6 class="section-title">
                                <i class="fas fa-file-alt mr-2"></i> Service Description
                            </h6>
                            <p id="viewServiceDescription" class="detail-text">-</p>
                        </div>

                        <div id="viewNotesSection" class="detail-section" style="display: none;">
                            <h6 class="section-title">
                                <i class="fas fa-sticky-note mr-2"></i> Notes
                            </h6>
                            <p id="viewNotes" class="detail-text">-</p>
                        </div>
                    </div>
                </div>

                <!-- Error State -->
                <div id="viewWorkerError" class="text-center py-5" style="display: none;">
                    <div class="empty-icon">
                        <i class="fas fa-exclamation-circle text-danger"></i>
                    </div>
                    <h4 class="empty-title">Failed to load worker details</h4>
                    <p id="viewWorkerErrorMessage" class="text-muted">Please try again.</p>
                    <button type="button" class="btn btn-primary mt-3" onclick="closeViewWorkerModal()">
                        <i class="fas fa-times mr-2"></i> Close
                    </button>
                </div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" id="viewSendBadgeBtn" class="btn btn-info" style="display: none;">
                    <i class="fas fa-id-card mr-2"></i> Send Badge
                </button>
                <button type="button" id="viewRevokeBadgeBtn" class="btn btn-danger" style="display: none;">
                    <i class="fas fa-ban mr-2"></i> Revoke Badge
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeViewWorkerModal()">
                    <i class="fas fa-times mr-2"></i> Close
                </button>
            </div>
        </div>
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
// ============================================ //
// VIEW WORKER MODAL FUNCTIONS (ADMIN VERSION) //
// ============================================ //

function openViewWorkerModal(workerId) {
    var modal = document.getElementById('viewWorkerModal');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    document.getElementById('viewWorkerLoading').style.display = 'block';
    document.getElementById('viewWorkerContent').style.display = 'none';
    document.getElementById('viewWorkerError').style.display = 'none';
    
    fetchWorkerDetails(workerId);
}

function closeViewWorkerModal() {
    document.getElementById('viewWorkerModal').classList.remove('show');
    document.body.style.overflow = '';
}

function fetchWorkerDetails(workerId) {
    var url = "{{ url('admin/workers') }}/" + workerId;
    
    fetch(url, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        credentials: 'same-origin'
    })
    .then(function(response) {
        if (!response.ok) {
            return response.text().then(function(text) {
                throw new Error('Server returned ' + response.status + ': ' + text.substring(0, 100));
            });
        }
        return response.text();
    })
    .then(function(text) {
        // Remove BOM characters for clean JSON parsing
        var cleanedText = text.replace(/^\uFEFF/, '').replace(/^\u00BB\uBF/, '').replace(/^\uFFFE/, '');
        if (cleanedText.charCodeAt(0) === 65279) {
            cleanedText = cleanedText.substring(1);
        }
        
        try {
            var data = JSON.parse(cleanedText);
            if (data.success) {
                displayAdminWorkerDetails(data.worker);
            } else {
                showWorkerError(data.message || 'Failed to load worker details.');
            }
        } catch (parseError) {
            showWorkerError('Invalid response format from server.');
        }
    })
    .catch(function(error) {
        showWorkerError('An error occurred while loading worker details: ' + error.message);
    });
}

function displayAdminWorkerDetails(worker) {
    document.getElementById('viewWorkerLoading').style.display = 'none';
    document.getElementById('viewWorkerContent').style.display = 'block';
    document.getElementById('viewWorkerError').style.display = 'none';
    
    // Basic Info
    document.getElementById('viewWorkerName').textContent = worker.full_name || '-';
    document.getElementById('viewFullName').textContent = worker.full_name || '-';
    document.getElementById('viewEmail').textContent = worker.email || 'Not provided';
    document.getElementById('viewPhone').textContent = worker.phone || 'Not provided';
    document.getElementById('viewIdNumber').textContent = worker.id_number || 'Not provided';
    document.getElementById('viewAddress').textContent = worker.address || 'Not provided';
    document.getElementById('viewJobTitle').textContent = worker.job_title || 'Not specified';
    document.getElementById('viewTrade').textContent = worker.trade ? ucfirst(worker.trade) : 'Not specified';
    document.getElementById('viewSpecialization').textContent = worker.specialization || 'Not specified';
    document.getElementById('viewDailyRate').textContent = worker.daily_rate ? '₵' + parseFloat(worker.daily_rate).toFixed(2) + ' / day' : 'Not set';
    document.getElementById('viewAddedOn').textContent = worker.created_at ? formatDate(worker.created_at) : 'Not available';
    
    // Status
    var statusColors = {
        'active': 'success',
        'inactive': 'warning',
        'completed': 'success',
        'terminated': 'danger'
    };
    var statusIcons = {
        'active': 'fa-play-circle',
        'inactive': 'fa-pause-circle',
        'completed': 'fa-flag-checkered',
        'terminated': 'fa-times-circle'
    };
    var statusColor = statusColors[worker.status] || 'secondary';
    var statusIcon = statusIcons[worker.status] || 'fa-question-circle';
    var statusDisplay = ucfirst(worker.status || 'unknown');
    
    document.getElementById('viewStatus').innerHTML = 
        '<span class="status-badge status-' + statusColor + '">' +
            '<i class="fas ' + statusIcon + ' mr-1"></i> ' + statusDisplay +
        '</span>';
    document.getElementById('viewWorkerStatus').innerHTML = 
        '<span class="status-badge status-' + statusColor + '">' +
            '<i class="fas ' + statusIcon + ' mr-1"></i> ' + statusDisplay +
        '</span>';
    
    // Trade
    if (worker.trade) {
        document.getElementById('viewWorkerTrade').innerHTML = 
            '<span class="trade-badge">' +
                '<i class="fas fa-tools mr-1"></i> ' + ucfirst(worker.trade) +
            '</span>';
    } else {
        document.getElementById('viewWorkerTrade').textContent = 'No trade specified';
    }

    // Contractor & Contract
    if (worker.contractor) {
        document.getElementById('viewContractorName').textContent = worker.contractor.name;
        document.getElementById('viewContractorFull').textContent = worker.contractor.name + (worker.contractor.company_name ? ' (' + worker.contractor.company_name + ')' : '');
        document.getElementById('viewAddedByName').textContent = worker.contractor.name;
    }
    if (worker.contract) {
        document.getElementById('viewContractNumber').textContent = worker.contract.contract_number;
        document.getElementById('viewContractFull').textContent = worker.contract.contract_number + ' - ' + worker.contract.title;
        document.getElementById('viewSiteLocation').textContent = worker.contract.location || 'Not specified';
        document.getElementById('viewSiteName').textContent = worker.contract.location || 'Not specified';
        document.getElementById('viewContractStatus').textContent = ucfirst(worker.contract.status || 'Unknown');
    }
    if (worker.added_by) {
        document.getElementById('viewAddedByName').textContent = worker.added_by.name || 'Unknown';
    }
    
    // Site Status
    var siteStatusEl = document.getElementById('viewWorkerSiteStatus');
    if (worker.is_assigned_to_site) {
        siteStatusEl.style.display = 'inline-flex';
        siteStatusEl.className = 'status-badge status-success';
        siteStatusEl.innerHTML = '<i class="fas fa-hard-hat mr-1"></i> On Site';
        document.getElementById('viewSiteStatus').textContent = '✅ Assigned to Site';
        document.getElementById('viewSiteStatus').style.color = 'var(--success)';
    } else {
        siteStatusEl.style.display = 'inline-flex';
        siteStatusEl.className = 'status-badge status-secondary';
        siteStatusEl.innerHTML = '<i class="fas fa-user mr-1"></i> Not on Site';
        document.getElementById('viewSiteStatus').textContent = '❌ Not Assigned to Site';
        document.getElementById('viewSiteStatus').style.color = 'var(--text-secondary)';
    }
    
    // Badge Information
    var badgeSection = document.getElementById('viewBadgeSection');
    if (worker.badge) {
        badgeSection.style.display = 'block';
        document.getElementById('viewBadgeNumber').textContent = worker.badge.badge_number || '-';
        document.getElementById('viewBadgeValidFrom').textContent = worker.badge.valid_from ? formatDate(worker.badge.valid_from) : 'Not set';
        document.getElementById('viewBadgeValidUntil').textContent = worker.badge.valid_until ? formatDate(worker.badge.valid_until) : 'Not set';
        document.getElementById('viewBadgeEmailSent').textContent = worker.badge.email_sent_at ? formatDate(worker.badge.email_sent_at) : 'Not sent';
        document.getElementById('viewVerificationCount').textContent = worker.badge.verification_count || 0;
        
        // Badge Status
        var badgeStatusColors = {
            'active': 'success',
            'expired': 'danger',
            'inactive': 'secondary',
            'pending': 'warning'
        };
        var badgeStatusDisplay = ucfirst(worker.badge.status || 'unknown');
        var badgeColor = badgeStatusColors[worker.badge.status] || 'secondary';
        document.getElementById('viewBadgeStatus').innerHTML = 
            '<span class="status-badge status-' + badgeColor + '">' +
                '<i class="fas ' + (worker.badge.status === 'active' ? 'fa-check-circle' : 'fa-exclamation-circle') + ' mr-1"></i> ' + 
                badgeStatusDisplay +
            '</span>';

        // Show badge status in header
        var badgeStatusEl = document.getElementById('viewWorkerBadgeStatus');
        badgeStatusEl.style.display = 'inline-flex';
        badgeStatusEl.className = 'status-badge status-' + badgeColor;
        badgeStatusEl.innerHTML = '<i class="fas fa-id-card mr-1"></i> ' + badgeStatusDisplay;

        // Verification URL
        if (worker.badge.verification_url) {
            var urlEl = document.getElementById('viewBadgeVerificationUrl');
            var link = urlEl.querySelector('a');
            if (link) {
                link.href = worker.badge.verification_url;
            }
        }
        
        // Days until expiry
        if (worker.badge.days_until_expiry !== null && worker.badge.days_until_expiry !== undefined) {
            document.getElementById('viewDaysUntilExpiry').textContent = worker.badge.days_until_expiry + ' days';
            if (worker.badge.days_until_expiry < 0) {
                document.getElementById('viewDaysUntilExpiry').textContent = 'Expired ' + Math.abs(worker.badge.days_until_expiry) + ' days ago';
                document.getElementById('viewDaysUntilExpiry').style.color = 'var(--danger)';
            }
        } else {
            document.getElementById('viewDaysUntilExpiry').textContent = 'N/A';
        }

        // Send Badge button in footer
        var sendBtn = document.getElementById('viewSendBadgeBtn');
        sendBtn.style.display = 'inline-flex';
        sendBtn.onclick = function() {
            closeViewWorkerModal();
            if (worker.badge) {
                sendBadge(worker.id, 'resend');
            } else {
                sendBadge(worker.id, 'send');
            }
        };

        // Revoke Badge button in footer
        var revokeBtn = document.getElementById('viewRevokeBadgeBtn');
        if (worker.badge.is_active && !worker.badge.is_expired) {
            revokeBtn.style.display = 'inline-flex';
            revokeBtn.onclick = function() {
                closeViewWorkerModal();
                revokeBadge(worker.id);
            };
        } else {
            revokeBtn.style.display = 'none';
        }
    } else {
        badgeSection.style.display = 'none';
        document.getElementById('viewWorkerBadgeStatus').style.display = 'none';
        document.getElementById('viewSendBadgeBtn').style.display = 'none';
        document.getElementById('viewRevokeBadgeBtn').style.display = 'none';
        document.getElementById('viewVerificationCount').textContent = '0';
        document.getElementById('viewDaysUntilExpiry').textContent = 'No badge';
    }
    
    // Skills
    var skillsContainer = document.getElementById('viewSkills');
    var skillsSection = document.getElementById('viewSkillsSection');
    if (worker.skills && Array.isArray(worker.skills) && worker.skills.length > 0) {
        skillsSection.style.display = 'block';
        skillsContainer.innerHTML = '';
        worker.skills.forEach(function(skill) {
            if (skill) {
                var skillBadge = document.createElement('span');
                skillBadge.className = 'skill-badge';
                skillBadge.textContent = ucfirst(skill);
                skillsContainer.appendChild(skillBadge);
            }
        });
    } else {
        skillsSection.style.display = 'none';
    }
    
    // Description
    var descSection = document.getElementById('viewDescriptionSection');
    if (worker.service_description) {
        descSection.style.display = 'block';
        document.getElementById('viewServiceDescription').textContent = worker.service_description;
    } else {
        descSection.style.display = 'none';
    }
    
    // Notes
    var notesSection = document.getElementById('viewNotesSection');
    if (worker.notes) {
        notesSection.style.display = 'block';
        document.getElementById('viewNotes').textContent = worker.notes;
    } else {
        notesSection.style.display = 'none';
    }
}

function showWorkerError(message) {
    document.getElementById('viewWorkerLoading').style.display = 'none';
    document.getElementById('viewWorkerContent').style.display = 'none';
    document.getElementById('viewWorkerError').style.display = 'block';
    document.getElementById('viewWorkerErrorMessage').textContent = message || 'Unable to load worker details.';
}

// ============================================ //
// REFRESH PAGE //
// ============================================ //
function refreshPage() {
    window.location.reload();
}

// ============================================ //
// REVOKE BADGE //
// ============================================ //
function revokeBadge(workerId) {
    if (!confirm('Are you sure you want to revoke this worker\'s badge? This action cannot be undone.')) {
        return;
    }
    
    showLoading('Revoking badge...');
    
    var url = "{{ url('admin/workers') }}/" + workerId + "/revoke-badge";
    
    fetch(url, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        credentials: 'same-origin'
    })
    .then(function(response) {
        if (!response.ok) {
            return response.text().then(function(text) {
                var cleanText = text.replace(/^\uFEFF/, '').replace(/^\u00BB\u00BF/, '').replace(/^\uFFFE/, '');
                try {
                    var errorData = JSON.parse(cleanText);
                    throw new Error(errorData.message || 'Server returned ' + response.status);
                } catch (e) {
                    throw new Error('Server returned ' + response.status + ': ' + cleanText.substring(0, 100));
                }
            });
        }
        return response.json();
    })
    .then(function(data) {
        hideLoading();
        if (data.success) {
            showToast('Badge revoked successfully!', 'success');
            setTimeout(refreshPage, 1500);
        } else {
            showToast(data.message || 'Failed to revoke badge.', 'error');
        }
    })
    .catch(function(error) {
        hideLoading();
        console.error('Error revoking badge:', error);
        showToast('Error: ' + error.message, 'error');
    });
}

// ============================================ //
// SEND BADGE //
// ============================================ //
function sendBadge(workerId, action) {
    var url = "{{ url('admin/workers') }}/" + workerId + "/" + (action === 'resend' ? 'resend-badge' : 'send-badge');
    
    var confirmMsg = action === 'resend' ? 
        'Are you sure you want to resend the badge to this worker?' : 
        'Are you sure you want to send a badge to this worker?';
    
    if (!confirm(confirmMsg)) {
        return;
    }
    
    showLoading('Sending badge...');
    
    fetch(url, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        credentials: 'same-origin'
    })
    .then(function(response) {
        if (!response.ok) {
            return response.text().then(function(text) {
                var cleanText = text.replace(/^\uFEFF/, '').replace(/^\u00BB\u00BF/, '').replace(/^\uFFFE/, '');
                try {
                    var errorData = JSON.parse(cleanText);
                    throw new Error(errorData.message || 'Server returned ' + response.status);
                } catch (e) {
                    throw new Error('Server returned ' + response.status + ': ' + cleanText.substring(0, 100));
                }
            });
        }
        return response.json();
    })
    .then(function(data) {
        hideLoading();
        if (data.success) {
            showToast(data.message || 'Badge sent successfully!', 'success');
            setTimeout(refreshPage, 1500);
        } else {
            showToast(data.message || 'Failed to send badge.', 'error');
        }
    })
    .catch(function(error) {
        hideLoading();
        console.error('Error sending badge:', error);
        showToast('Error: ' + error.message, 'error');
    });
}

// ============================================ //
// TOAST SYSTEM //
// ============================================ //
function showToast(message, type) {
    var toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(toastContainer);
    }
    
    var toast = document.createElement('div');
    toast.className = 'px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full';
    
    var colors = {
        'success': 'bg-green-100 text-green-800 border border-green-200',
        'error': 'bg-red-100 text-red-800 border border-red-200',
        'warning': 'bg-yellow-100 text-yellow-800 border border-yellow-200',
        'info': 'bg-blue-100 text-blue-800 border border-blue-200'
    };
    toast.className += ' ' + (colors[type] || colors.info);
    
    var iconMap = {
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
    
    setTimeout(function() {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);
    
    setTimeout(function() {
        if (toast.parentNode === toastContainer) {
            toast.classList.add('translate-x-full');
            setTimeout(function() { toast.remove(); }, 300);
        }
    }, 5000);
}

// ============================================ //
// LOADING OVERLAY //
// ============================================ //
function showLoading(message) {
    var overlay = document.getElementById('loadingOverlay');
    var loadingMessage = document.getElementById('loadingMessage');
    if (overlay && loadingMessage) {
        loadingMessage.textContent = message || 'Processing...';
        overlay.classList.remove('hidden');
    }
}

function hideLoading() {
    var overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.add('hidden');
    }
}

// ============================================ //
// UTILITY FUNCTIONS //
// ============================================ //
function formatDate(dateString) {
    if (!dateString) return 'Not set';
    var date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function ucfirst(string) {
    if (!string) return '';
    return string.charAt(0).toUpperCase() + string.slice(1);
}

// ============================================ //
// CLOSE MODALS WITH ESCAPE KEY //
// ============================================ //
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var modal = document.getElementById('viewWorkerModal');
        if (modal && modal.classList.contains('show')) {
            closeViewWorkerModal();
        }
    }
});

// ============================================ //
// CLOSE MODALS ON OVERLAY CLICK //
// ============================================ //
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('custom-modal-overlay')) {
        var modal = e.target.closest('.custom-modal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    }
});

// ============================================ //
// AUTO-HIDE ALERTS //
// ============================================ //
document.addEventListener('DOMContentLoaded', function() {
    var alerts = document.querySelectorAll('.bg-green-100, .bg-red-100, .bg-yellow-100');
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

console.log('🚀 Admin Site Worker Management Loaded');
</script>

<style>
/* ============================================ */
/* CUSTOM MODAL CSS - Theme Aware */
/* ============================================ */
.custom-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    overflow-y: auto;
    padding: 1rem;
}

.custom-modal.show {
    display: flex !important;
    align-items: center;
    justify-content: center;
}

.custom-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1;
}

.custom-modal-dialog {
    position: relative;
    z-index: 2;
    max-width: 800px;
    width: 100%;
    margin: auto;
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Theme-aware modal content */
.custom-modal-content.theme-aware {
    background-color: var(--card-bg, #ffffff);
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    border: 1px solid var(--border-color, #e5e7eb);
}

.custom-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color, #e5e7eb);
    background-color: var(--bg-secondary, #f3f4f6);
}

.custom-modal-title {
    font-size: 1.125rem;
    font-weight: 600;
    margin: 0;
    color: var(--text-primary, #1f2937);
}

.custom-modal-close {
    background: none;
    border: none;
    font-size: 1.75rem;
    line-height: 1;
    color: var(--text-secondary, #6b7280);
    cursor: pointer;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    transition: background-color 0.2s;
}

.custom-modal-close:hover {
    background-color: rgba(0, 0, 0, 0.05);
    color: var(--text-primary, #1f2937);
}

.custom-modal-body {
    padding: 1.5rem;
    max-height: 70vh;
    overflow-y: auto;
    background-color: var(--card-bg, #ffffff);
}

.custom-modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color, #e5e7eb);
    gap: 0.5rem;
    background-color: var(--bg-secondary, #f3f4f6);
}

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

.btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background-color: rgba(var(--secondary-rgb), 0.2) !important;
    transform: translateY(-1px);
}

.btn-info {
    background-color: var(--info) !important;
    color: #ffffff !important;
    border: 1px solid var(--info) !important;
    transition: all 0.2s ease;
}

.btn-info:hover {
    background-color: #0891b2 !important;
    border-color: #0891b2 !important;
    transform: translateY(-1px);
}

.btn-danger {
    background-color: var(--danger) !important;
    color: #ffffff !important;
    border: 1px solid var(--danger) !important;
    transition: all 0.2s ease;
}

.btn-danger:hover {
    background-color: #b91c1c !important;
    border-color: #b91c1c !important;
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
/* TABLE STYLES */
/* ============================================ */
table th, table td {
    padding: 0.75rem 1rem;
}

tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.02) !important;
}

/* ============================================ */
/* WORKER PROFILE STYLES */
/* ============================================ */
.worker-profile {
    padding: 0.5rem 0;
}

.worker-profile-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    margin-bottom: 1.5rem;
}

.worker-avatar {
    width: 4rem;
    height: 4rem;
    border-radius: 50%;
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    flex-shrink: 0;
}

.worker-name-display {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.worker-header-meta {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    align-items: center;
}

.meta-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.meta-tag i {
    font-size: 0.688rem;
}

.worker-details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.detail-section {
    background-color: var(--bg-secondary);
    border-radius: 8px;
    padding: 1rem;
    border: 1px solid var(--border-color);
}

.section-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.75rem 0;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border-color);
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.375rem 0;
    border-bottom: 1px solid rgba(var(--border-color), 0.3);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-size: 0.813rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.detail-value {
    font-size: 0.813rem;
    color: var(--text-primary);
    text-align: right;
    max-width: 60%;
    word-break: break-word;
}

.detail-text {
    color: var(--text-primary);
    font-size: 0.875rem;
    margin: 0;
    line-height: 1.6;
    white-space: pre-wrap;
}

.skills-container {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.skill-badge {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
}

.overview-card {
    background: var(--bg-secondary);
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    text-align: center;
}

.overview-card .overview-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.overview-card .overview-value {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-top: 0.25rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    white-space: nowrap;
}

.status-success {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
}

.status-warning {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
}

.status-danger {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
}

.status-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--secondary);
}

.trade-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
}

.empty-icon {
    font-size: 3rem;
    color: var(--text-muted);
    opacity: 0.5;
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

.spinner-border {
    display: inline-block;
    width: 2rem;
    height: 2rem;
    border: 0.25em solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: spinner-border 0.75s linear infinite;
    color: var(--primary, #2563eb);
}

@keyframes spinner-border {
    to { transform: rotate(360deg); }
}

.visually-hidden {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    border: 0 !important;
}

/* ============================================ */
/* RESPONSIVE OVERRIDES */
/* ============================================ */
@media (max-width: 768px) {
    .worker-details-grid {
        grid-template-columns: 1fr;
    }
    
    .worker-profile-header {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    .worker-header-meta {
        justify-content: center;
    }
    
    .detail-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.125rem;
    }
    
    .detail-value {
        text-align: left;
        max-width: 100%;
    }
    
    .admin-overview-grid {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}

@media (max-width: 480px) {
    .custom-modal-dialog {
        margin: 0.5rem;
    }
    
    .custom-modal-body {
        max-height: 60vh;
    }
    
    .worker-avatar {
        width: 3rem;
        height: 3rem;
        font-size: 1.75rem;
    }
    
    .worker-name-display {
        font-size: 1rem;
    }
    
    .admin-overview-grid {
        grid-template-columns: 1fr !important;
    }
}

/* ============================================ */
/* CSS VARIABLES (Theme fallbacks) */
/* ============================================ */
:root {
    --primary: #2563eb;
    --primary-rgb: 37, 99, 235;
    --secondary: #6b7280;
    --secondary-rgb: 107, 114, 128;
    --success: #16a34a;
    --success-rgb: 22, 163, 74;
    --warning: #f59e0b;
    --warning-rgb: 245, 158, 11;
    --danger: #dc2626;
    --danger-rgb: 220, 38, 38;
    --info: #06b6d4;
    --info-rgb: 6, 182, 212;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --text-muted: #9ca3af;
    --border-color: #e5e7eb;
    --card-bg: #ffffff;
    --bg-secondary: #f3f4f6;
}

/* Dark theme overrides */
[data-theme="dark"] .custom-modal-content.theme-aware {
    background-color: var(--card-bg, #2a2a3c);
    border-color: var(--border-color, #39394a);
}

[data-theme="dark"] .custom-modal-header {
    background-color: var(--bg-secondary, #2a2a3c);
    border-bottom-color: var(--border-color, #39394a);
}

[data-theme="dark"] .custom-modal-body {
    background-color: var(--card-bg, #2a2a3c);
}

[data-theme="dark"] .custom-modal-footer {
    background-color: var(--bg-secondary, #2a2a3c);
    border-top-color: var(--border-color, #39394a);
}

[data-theme="dark"] .custom-modal-title {
    color: var(--text-primary, #e4e4e4);
}

[data-theme="dark"] .custom-modal-close {
    color: var(--text-secondary, #a0a0a0);
}

[data-theme="dark"] .custom-modal-close:hover {
    background-color: rgba(255, 255, 255, 0.05);
    color: var(--text-primary, #e4e4e4);
}

[data-theme="dark"] .detail-section {
    background-color: var(--bg-secondary, #2a2a3c);
    border-color: var(--border-color, #39394a);
}

[data-theme="dark"] .detail-row {
    border-bottom-color: rgba(var(--border-color, #39394a), 0.3);
}

[data-theme="dark"] .section-title {
    border-bottom-color: var(--border-color, #39394a);
}

[data-theme="dark"] .detail-label {
    color: var(--text-secondary, #a0a0a0);
}

[data-theme="dark"] .detail-value {
    color: var(--text-primary, #e4e4e4);
}

[data-theme="dark"] .detail-text {
    color: var(--text-primary, #e4e4e4);
}

[data-theme="dark"] .skill-badge {
    background-color: rgba(var(--primary-rgb, 140, 130, 255), 0.2);
    color: var(--primary, #8c82ff);
}

[data-theme="dark"] .worker-avatar {
    background-color: rgba(var(--primary-rgb, 140, 130, 255), 0.2);
    color: var(--primary, #8c82ff);
}

[data-theme="dark"] .worker-name-display {
    color: var(--text-primary, #e4e4e4);
}

[data-theme="dark"] .overview-card {
    background: var(--bg-secondary, #2a2a3c);
    border-color: var(--border-color, #39394a);
}

[data-theme="dark"] .overview-card .overview-value {
    color: var(--text-primary, #e4e4e4);
}
</style>
@endsection