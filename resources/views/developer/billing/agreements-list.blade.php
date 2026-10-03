{{-- resources/views/developer/billing/agreements-list.blade.php --}}
@extends('layouts.dev')

@php
    $pageTitle = 'Billing Agreements';

    function formatCurrency($amount, $currency = 'GHS') {
        if (empty($amount)) return 'GH₵0.00';
        if ($currency === 'GHS') {
            return 'GH₵' . number_format($amount, 2);
        }
        return $currency . ' ' . number_format($amount, 2);
    }

    function getStatusColor($status) {
        $colors = [
            'active' => 'success',
            'pending' => 'warning',
            'completed' => 'info',
            'terminated' => 'danger',
            'superseded' => 'secondary',
            'cancelled' => 'secondary'
        ];
        return $colors[$status] ?? 'secondary';
    }

    function getPaymentStatusColor($status) {
        $colors = [
            'paid' => 'success',
            'partial' => 'warning',
            'unpaid' => 'danger',
            'overdue' => 'danger'
        ];
        return $colors[$status] ?? 'secondary';
    }

    function getFrequencyColor($frequency) {
        $colors = [
            'monthly' => 'primary',
            'quarterly' => 'info',
            'yearly' => 'warning',
            'one_time' => 'secondary'
        ];
        return $colors[$frequency] ?? 'secondary';
    }
@endphp

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center p-6 gap-4">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-list-alt text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-list-alt mr-2" style="color: var(--primary);"></i> 
                        Billing Agreements
                    </h2>
                    <div class="text-sm flex flex-wrap items-center mt-1 gap-2" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        <span>View and manage all billing agreements</span>
                        <span>•</span>
                        <i class="fas fa-circle mr-1" style="color: var(--success);"></i>
                        <span class="font-medium">{{ number_format($agreements->total()) }} total agreements</span>
                        @if(($stats['trashed_count'] ?? 0) > 0)
                        <span>•</span>
                        <i class="fas fa-trash-alt mr-1" style="color: var(--danger);"></i>
                        <a href="{{ route('developer.billing.agreements-trashed') }}" 
                           class="font-medium hover:underline"
                           style="color: var(--danger);">
                            {{ number_format($stats['trashed_count']) }} in trash
                        </a>
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                </div>
                <div class="flex items-center space-x-2 mt-2">
                    <a href="{{ route('developer.billing.dashboard') }}" 
                       class="px-3 py-1.5 rounded-lg text-xs font-medium inline-flex items-center transition-all duration-200 hover:transform hover:-translate-y-0.5" 
                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-arrow-left mr-1"></i> Dashboard
                    </a>
                    <a href="{{ route('developer.billing.agreements-trashed') }}" 
                       class="px-3 py-1.5 rounded-lg text-xs font-medium inline-flex items-center transition-all duration-200 hover:transform hover:-translate-y-0.5" 
                       style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                        <i class="fas fa-trash-alt mr-1"></i> Trash
                        @if(($stats['trashed_count'] ?? 0) > 0)
                        <span class="ml-1 px-1.5 py-0.5 rounded-full text-xs" style="background-color: var(--danger); color: white;">
                            {{ number_format($stats['trashed_count']) }}
                        </span>
                        @endif
                    </a>
                    <button onclick="scrollToCreateForm()" 
                            class="px-3 py-1.5 rounded-lg text-xs font-medium inline-flex items-center transition-all duration-200 hover:transform hover:-translate-y-0.5" 
                            style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <i class="fas fa-plus mr-1"></i> New Agreement
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="card stat-card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Agreements</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($stats['total_agreements'] ?? 0) }}</p>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    <i class="fas fa-file-contract text-lg"></i>
                </div>
            </div>
        </div>
        
        <div class="card stat-card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Active</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($stats['active_agreements'] ?? 0) }}</p>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                    <i class="fas fa-check-circle text-lg"></i>
                </div>
            </div>
        </div>
        
        <div class="card stat-card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Pending</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($stats['pending_agreements'] ?? 0) }}</p>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                    <i class="fas fa-clock text-lg"></i>
                </div>
            </div>
        </div>
        
        <div class="card stat-card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Completed</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($stats['completed_agreements'] ?? 0) }}</p>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                    <i class="fas fa-check-double text-lg"></i>
                </div>
            </div>
        </div>
        
        <div class="card stat-card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">In Trash</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($stats['trashed_count'] ?? 0) }}</p>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                    <i class="fas fa-trash-alt text-lg"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Stats Row -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Amount Agreed</p>
                    <p class="text-xl font-bold" style="color: var(--text-primary);">{{ formatCurrency($stats['total_amount_agreed'] ?? 0) }}</p>
                </div>
                <i class="fas fa-hand-holding-usd text-2xl opacity-50" style="color: var(--primary);"></i>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Amount Received</p>
                    <p class="text-xl font-bold" style="color: var(--success);">{{ formatCurrency($stats['total_amount_received'] ?? 0) }}</p>
                </div>
                <i class="fas fa-money-bill-wave text-2xl opacity-50" style="color: var(--success);"></i>
            </div>
        </div>
        
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Collection Rate</p>
                    <p class="text-xl font-bold" style="color: var(--text-primary);">
                        @php
                            $totalAgreed = $stats['total_amount_agreed'] ?? 0;
                            $totalReceived = $stats['total_amount_received'] ?? 0;
                            $collectionRate = $totalAgreed > 0 ? round(($totalReceived / $totalAgreed) * 100, 1) : 0;
                        @endphp
                        {{ $collectionRate }}%
                    </p>
                </div>
                <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var({{ $collectionRate >= 75 ? '--success' : ($collectionRate >= 50 ? '--warning' : '--danger') }}-rgb), 0.1);">
                    <i class="fas fa-chart-line text-lg" style="color: var({{ $collectionRate >= 75 ? '--success' : ($collectionRate >= 50 ? '--warning' : '--danger') }});"></i>
                </div>
            </div>
            <div class="mt-2">
                <div class="w-full rounded-full h-1.5" style="background-color: rgba(var(--secondary-rgb), 0.2);">
                    <div class="rounded-full h-1.5 transition-all duration-500" style="width: {{ $collectionRate }}%; background-color: var({{ $collectionRate >= 75 ? '--success' : ($collectionRate >= 50 ? '--warning' : '--danger') }});"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filter Agreements
        </h3>
        
        <form method="GET" action="{{ route('developer.billing.agreements-list') }}" class="space-y-4" id="filterForm">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Status Filter -->
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Status</label>
                    <select name="status" class="form-select w-full p-2.5 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);" onchange="this.form.submit()">
                        <option value="all" {{ request('status') == 'all' ? 'selected' : '' }}>All Statuses</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="terminated" {{ request('status') == 'terminated' ? 'selected' : '' }}>Terminated</option>
                        <option value="superseded" {{ request('status') == 'superseded' ? 'selected' : '' }}>Superseded</option>
                    </select>
                </div>
                
                <!-- Payment Status Filter -->
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Payment Status</label>
                    <select name="payment_status" class="form-select w-full p-2.5 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);" onchange="this.form.submit()">
                        <option value="all" {{ request('payment_status') == 'all' ? 'selected' : '' }}>All Payment Statuses</option>
                        <option value="paid" {{ request('payment_status') == 'paid' ? 'selected' : '' }}>Paid</option>
                        <option value="partial" {{ request('payment_status') == 'partial' ? 'selected' : '' }}>Partial</option>
                        <option value="unpaid" {{ request('payment_status') == 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                        <option value="overdue" {{ request('payment_status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                    </select>
                </div>
                
                <!-- Frequency Filter -->
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Billing Frequency</label>
                    <select name="frequency" class="form-select w-full p-2.5 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);" onchange="this.form.submit()">
                        <option value="all" {{ request('frequency') == 'all' ? 'selected' : '' }}>All Frequencies</option>
                        <option value="monthly" {{ request('frequency') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                        <option value="quarterly" {{ request('frequency') == 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                        <option value="yearly" {{ request('frequency') == 'yearly' ? 'selected' : '' }}>Yearly</option>
                        <option value="one_time" {{ request('frequency') == 'one_time' ? 'selected' : '' }}>One Time</option>
                    </select>
                </div>
                
                <!-- Primary Only Filter -->
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Primary Contact</label>
                    <select name="primary_only" class="form-select w-full p-2.5 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);" onchange="this.form.submit()">
                        <option value="false" {{ request('primary_only') != 'true' ? 'selected' : '' }}>All Agreements</option>
                        <option value="true" {{ request('primary_only') == 'true' ? 'selected' : '' }}>Primary Only</option>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Date Range -->
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Date Range</label>
                    <select name="date_range" id="date_range" class="form-select w-full p-2.5 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);" onchange="toggleCustomDates(this.value)">
                        <option value="all" {{ request('date_range') == 'all' ? 'selected' : '' }}>All Time</option>
                        <option value="today" {{ request('date_range') == 'today' ? 'selected' : '' }}>Today</option>
                        <option value="this_week" {{ request('date_range') == 'this_week' ? 'selected' : '' }}>This Week</option>
                        <option value="this_month" {{ request('date_range') == 'this_month' ? 'selected' : '' }}>This Month</option>
                        <option value="last_month" {{ request('date_range') == 'last_month' ? 'selected' : '' }}>Last Month</option>
                        <option value="this_year" {{ request('date_range') == 'this_year' ? 'selected' : '' }}>This Year</option>
                        <option value="custom" {{ request('date_range') == 'custom' ? 'selected' : '' }}>Custom Range</option>
                    </select>
                </div>
                
                <!-- Search -->
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Search</label>
                    <div class="relative">
                        <input type="text" 
                               name="search" 
                               value="{{ request('search') }}" 
                               class="form-input w-full p-2.5 rounded-lg border pl-9"
                               style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                               placeholder="Agreement #, description, or super admin...">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-sm" style="color: var(--text-secondary);"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Custom Date Range -->
            <div id="customDateRange" class="grid grid-cols-1 md:grid-cols-2 gap-4 {{ request('date_range') != 'custom' ? 'hidden' : '' }}">
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Start Date</label>
                    <input type="date" name="start_date" value="{{ request('start_date') }}" class="form-input w-full p-2.5 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">End Date</label>
                    <input type="date" name="end_date" value="{{ request('end_date') }}" class="form-input w-full p-2.5 rounded-lg border" style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                </div>
            </div>
            
            <!-- Filter Buttons -->
            <div class="flex flex-wrap justify-between items-center gap-3 pt-4" style="border-top: 1px solid var(--border-color);">
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('developer.billing.agreements-list') }}" 
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-sm transition-all duration-200 hover:transform hover:-translate-y-0.5"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-redo mr-2"></i> Reset Filters
                    </a>
                    <a href="{{ route('developer.billing.agreements-trashed') }}" 
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-sm transition-all duration-200 hover:transform hover:-translate-y-0.5"
                       style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); border: 1px solid rgba(var(--danger-rgb), 0.3);">
                        <i class="fas fa-trash-alt mr-2"></i> Trash
                        @if(($stats['trashed_count'] ?? 0) > 0)
                        <span class="ml-1 px-1.5 py-0.5 rounded-full text-xs" style="background-color: var(--danger); color: white;">
                            {{ number_format($stats['trashed_count']) }}
                        </span>
                        @endif
                    </a>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" onclick="exportAgreements()" 
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-sm transition-all duration-200 hover:transform hover:-translate-y-0.5"
                            style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <i class="fas fa-download mr-2"></i> Export
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-sm text-white transition-all duration-200 hover:transform hover:-translate-y-0.5 btn-primary">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Agreements Table -->
    <div class="card p-6">
        <div class="flex flex-wrap justify-between items-center mb-4 gap-3">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-table mr-2" style="color: var(--primary);"></i> Agreements List
            </h3>
            <div class="text-sm" style="color: var(--text-secondary);">
                Showing {{ $agreements->firstItem() }} - {{ $agreements->lastItem() }} of {{ $agreements->total() }}
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Agreement</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Super Admin</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Amount</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Frequency</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Status</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Payment</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Signatures</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Start Date</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($agreements as $agreement)
                    @php
                        if($agreement->trashed()) continue;
                        
                        $developerSigned = $agreement->signatures ? $agreement->signatures->where('signature_type', 'developer')->isNotEmpty() : false;
                        $superAdminSigned = $agreement->signatures ? $agreement->signatures->where('signature_type', 'super_admin')->isNotEmpty() : false;
                        $signatureCount = ($agreement->signatures ? $agreement->signatures->count() : 0);
                        
                        $canDelete = in_array($agreement->status, ['pending', 'terminated', 'cancelled']) && $agreement->amount_received == 0;
                        $canUpdate = in_array($agreement->status, ['active', 'pending']);
                        $canTerminate = $agreement->status == 'active';
                        $canRecordPayment = $agreement->status == 'active';
                        $canSign = $agreement->status == 'pending' && !$developerSigned;
                    @endphp
                    <tr style="border-bottom: 1px solid var(--border-color); transition: all 0.2s ease;">
                        <td class="p-3">
                            <div>
                                <div class="font-mono text-sm font-medium flex items-center flex-wrap gap-1" style="color: var(--text-primary);">
                                    @if($agreement->is_primary_for_billing)
                                        <i class="fas fa-crown text-xs" style="color: var(--primary);" title="Primary Billing Contact"></i>
                                    @endif
                                    {{ $agreement->agreement_number }}
                                </div>
                                <div class="text-xs mt-1 truncate max-w-[200px]" style="color: var(--text-secondary);" title="{{ $agreement->description }}">
                                    {{ Str::limit($agreement->description, 40) }}
                                </div>
                            </div>
                        </td>
                        <td class="p-3">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2 flex-shrink-0"
                                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    <i class="fas fa-user-shield text-xs"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-sm truncate" style="color: var(--text-primary);" title="{{ $agreement->superAdmin->name ?? 'N/A' }}">
                                        {{ $agreement->superAdmin->name ?? 'N/A' }}
                                    </div>
                                    <div class="text-xs truncate" style="color: var(--text-secondary);" title="{{ $agreement->superAdmin->email ?? '' }}">
                                        {{ $agreement->superAdmin->email ?? '' }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="p-3">
                            <div class="font-medium" style="color: var(--text-primary);">{{ formatCurrency($agreement->amount, $agreement->currency) }}</div>
                            <div class="text-xs {{ $agreement->amount_received >= $agreement->amount ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400' }}">
                                Rec: {{ formatCurrency($agreement->amount_received, $agreement->currency) }}
                            </div>
                        </td>
                        <td class="p-3">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ getFrequencyColor($agreement->billing_frequency) }}">
                                <i class="fas fa-{{ $agreement->billing_frequency == 'monthly' ? 'calendar-alt' : ($agreement->billing_frequency == 'quarterly' ? 'calendar-week' : ($agreement->billing_frequency == 'yearly' ? 'calendar-year' : 'calendar-check')) }} mr-1"></i>
                                {{ ucfirst($agreement->billing_frequency) }}
                            </span>
                        </td>
                        <td class="p-3">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ getStatusColor($agreement->status) }}">
                                <i class="fas fa-circle mr-1" style="font-size: 0.5rem;"></i>
                                {{ ucfirst($agreement->status) }}
                            </span>
                        </td>
                        <td class="p-3">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ getPaymentStatusColor($agreement->payment_status) }}">
                                <i class="fas fa-{{ $agreement->payment_status == 'paid' ? 'check-circle' : ($agreement->payment_status == 'partial' ? 'exclamation-triangle' : 'times-circle') }} mr-1"></i>
                                {{ ucfirst($agreement->payment_status) }}
                            </span>
                        </td>
                        <td class="p-3">
                            <div class="flex flex-col gap-1">
                                @if($developerSigned)
                                    <span class="inline-flex items-center text-xs text-green-600 dark:text-green-400">
                                        <i class="fas fa-check-circle mr-1"></i> Developer
                                    </span>
                                @endif
                                @if($superAdminSigned)
                                    <span class="inline-flex items-center text-xs text-green-600 dark:text-green-400">
                                        <i class="fas fa-check-circle mr-1"></i> Super Admin
                                    </span>
                                @endif
                                @if(!$developerSigned && !$superAdminSigned && $agreement->status == 'pending')
                                    <span class="inline-flex items-center text-xs text-yellow-600 dark:text-yellow-400">
                                        <i class="fas fa-clock mr-1"></i> Awaiting
                                    </span>
                                @endif
                                @if($developerSigned && !$superAdminSigned && $agreement->status == 'pending')
                                    <span class="inline-flex items-center text-xs text-blue-600 dark:text-blue-400">
                                        <i class="fas fa-hourglass-half mr-1"></i> Waiting for SA
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="p-3">
                            <div class="text-sm" style="color: var(--text-secondary);">{{ \Carbon\Carbon::parse($agreement->start_date)->format('M d, Y') }}</div>
                        </td>
                        <td class="p-3">
                            <div class="flex flex-wrap gap-1">
                                <!-- View Details -->
                                <a href="{{ route('developer.billing.view-agreement', $agreement->id) }}" 
                                   class="action-btn" 
                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                                   title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <!-- SIGN AGREEMENT BUTTON -->
                                @if($canSign)
                                <a href="{{ route('developer.billing.view-agreement-for-signing', $agreement->id) }}" 
                                   class="action-btn" 
                                   style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);"
                                   title="Sign Agreement">
                                    <i class="fas fa-signature"></i>
                                </a>
                                @endif
                                
                                <!-- Re-sign Button -->
                                @if($agreement->status == 'pending' && $developerSigned && !$superAdminSigned)
                                <button onclick="showResendSignatureModal({{ $agreement->id }})"
                                        class="action-btn" 
                                        style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);"
                                        title="Resend Signature Request">
                                    <i class="fas fa-envelope"></i>
                                </button>
                                @endif
                                
                                <!-- Update Agreement -->
                                @if($canUpdate)
                                <button onclick="showUpdateAgreementModal({{ $agreement->id }})"
                                        class="action-btn" 
                                        style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);"
                                        title="Update Agreement">
                                    <i class="fas fa-edit"></i>
                                </button>
                                @endif
                                
                                <!-- Record Payment -->
                                @if($canRecordPayment)
                                <button onclick="recordPayment({{ $agreement->id }})"
                                        class="action-btn" 
                                        style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);"
                                        title="Record Payment">
                                    <i class="fas fa-money-check-alt"></i>
                                </button>
                                @endif
                                
                                <!-- Send Reminder -->
                                @if($canUpdate)
                                <button type="button"
                                        onclick="sendReminder({{ $agreement->id }}, '{{ addslashes($agreement->agreement_number) }}', this)"
                                        class="action-btn"
                                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary);"
                                        title="Send Reminder">
                                    <i class="fas fa-bell"></i>
                                </button>
                                @endif
                                
                                <!-- Download PDF -->
                                @if($agreement->agreement_pdf_path)
                                <a href="{{ route('developer.billing.generate-agreement-pdf', $agreement->id) }}" 
                                   class="action-btn" 
                                   style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                                   title="Download PDF">
                                    <i class="fas fa-file-pdf"></i>
                                </a>
                                @endif
                                
                                <!-- Download Signed Agreement -->
                                @if($agreement->signed_agreement_pdf_path)
                                <a href="{{ route('developer.billing.download-signed-agreement', $agreement->id) }}" 
                                   class="action-btn" 
                                   style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);"
                                   title="Download Signed Agreement">
                                    <i class="fas fa-file-signature"></i>
                                </a>
                                @endif
                                
                                <!-- Signature Audit -->
                                @if($signatureCount > 0 || $agreement->signed_agreement_pdf_path)
                                <a href="{{ route('developer.billing.signature-audit', $agreement->id) }}" 
                                   class="action-btn" 
                                   style="background-color: rgba(128, 90, 213, 0.1); color: #805AD5;"
                                   title="View Signature Audit">
                                    <i class="fas fa-history"></i>
                                </a>
                                @endif
                                
                                <!-- Set as Primary Button -->
                                @if(!$agreement->is_primary_for_billing && $agreement->status == 'active')
                                <button onclick="showSetAsPrimaryModal({{ $agreement->id }}, '{{ addslashes($agreement->agreement_number) }}', '{{ addslashes($agreement->superAdmin->name ?? '') }}', '{{ addslashes($agreement->superAdmin->email ?? '') }}')"
                                        class="action-btn" 
                                        style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);"
                                        title="Set as Primary Billing Contact">
                                    <i class="fas fa-crown"></i>
                                </button>
                                @endif
                                
                                <!-- Terminate Agreement -->
                                @if($canTerminate)
                                <button onclick="showTerminateModal({{ $agreement->id }})"
                                        class="action-btn" 
                                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                                        title="Terminate Agreement">
                                    <i class="fas fa-ban"></i>
                                </button>
                                @endif
                                
                                <!-- Delete (Move to Trash) -->
                                @if($canDelete)
                                <button onclick="showDeleteAgreementModal({{ $agreement->id }}, '{{ $agreement->agreement_number }}')"
                                        class="action-btn" 
                                        style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);"
                                        title="Move to Trash">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="p-8 text-center" style="border-color: var(--border-color);">
                            <i class="fas fa-inbox text-4xl mb-3 opacity-50" style="color: var(--text-secondary);"></i>
                            <p class="text-lg font-medium mb-1" style="color: var(--text-primary);">No agreements found</p>
                            <p class="text-sm" style="color: var(--text-secondary);">
                                @if(request()->hasAny(['status', 'payment_status', 'frequency', 'search', 'date_range']))
                                    Try adjusting your filters
                                @else
                                    Get started by creating your first billing agreement
                                @endif
                            </p>
                            <div class="mt-4 flex justify-center gap-2">
                                @if(request()->hasAny(['status', 'payment_status', 'frequency', 'search', 'date_range']))
                                <a href="{{ route('developer.billing.agreements-list') }}" 
                                   class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center"
                                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                                    <i class="fas fa-redo mr-2"></i> Clear Filters
                                </a>
                                @endif
                                <button onclick="scrollToCreateForm()" 
                                        class="px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center text-white btn-primary">
                                    <i class="fas fa-plus mr-2"></i> Create Agreement
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        @if($agreements->hasPages())
        <div class="mt-6 pt-6 flex flex-col md:flex-row justify-between items-center gap-4" style="border-top: 1px solid var(--border-color);">
            <div class="text-sm" style="color: var(--text-secondary);">
                Showing {{ $agreements->firstItem() }} - {{ $agreements->lastItem() }} of {{ $agreements->total() }} results
            </div>
            <div class="flex items-center space-x-2">
                {{ $agreements->appends(request()->query())->links() }}
            </div>
            <div>
                <select onchange="updatePerPage(this.value)" 
                        class="form-select text-sm rounded-lg border p-2"
                        style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                    <option value="10" {{ request('per_page', 20) == 10 ? 'selected' : '' }}>10 per page</option>
                    <option value="20" {{ request('per_page', 20) == 20 ? 'selected' : '' }}>20 per page</option>
                    <option value="50" {{ request('per_page', 20) == 50 ? 'selected' : '' }}>50 per page</option>
                    <option value="100" {{ request('per_page', 20) == 100 ? 'selected' : '' }}>100 per page</option>
                </select>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Delete Agreement Modal (Soft Delete) -->
<div id="deleteAgreementModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm" onclick="closeModal('deleteAgreementModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container" style="background-color: var(--card-bg); border: 1px solid var(--border-color); max-width: 500px; width: 100%;">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--danger);"></i> Move to Trash
                </h3>
                <button type="button" onclick="closeModal('deleteAgreementModal')" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="deleteAgreementForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <div class="space-y-4">
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--warning);"></i>
                                <div>
                                    <p class="font-bold" style="color: var(--warning);">Move to Trash?</p>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                        This agreement will be moved to the trash. You can restore it later from the trash page.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                Type <strong id="deleteAgreementNumber" style="color: var(--danger);"></strong> to confirm:
                            </label>
                            <input type="text" 
                                   id="deleteConfirmationText"
                                   class="form-input w-full p-3 rounded-lg border font-mono"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                   placeholder="Enter agreement number"
                                   autocomplete="off"
                                   required>
                            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle mr-1"></i>
                                Please enter the exact agreement number to confirm moving to trash.
                            </p>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('deleteAgreementModal')">
                    Cancel
                </button>
                <button type="submit" 
                        form="deleteAgreementForm"
                        id="deleteConfirmButton"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background-color: var(--warning); border: 1px solid var(--warning); opacity: 0.5; cursor: not-allowed;"
                        disabled>
                    <i class="fas fa-trash-alt mr-2"></i> Move to Trash
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Update Agreement Modal -->
<div id="updateAgreementModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm" onclick="closeModal('updateAgreementModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container" style="background-color: var(--card-bg); border: 1px solid var(--border-color); max-width: 550px; width: 100%;">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-edit mr-2" style="color: var(--warning);"></i> Update Agreement
                </h3>
                <button type="button" onclick="closeModal('updateAgreementModal')" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="updateAgreementForm" method="POST" action="">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="agreement_id" id="updateAgreementId">
                    <div class="space-y-4">
                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                New Amount *
                            </label>
                            <input type="number" 
                                   name="amount" 
                                   id="updateAmount"
                                   class="form-input w-full p-3 rounded-lg border"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                   min="0" 
                                   step="0.01"
                                   required>
                        </div>
                        
                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                Description *
                            </label>
                            <textarea name="description" 
                                      id="updateDescription"
                                      class="form-input w-full p-3 rounded-lg border"
                                      style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                      rows="3"
                                      required></textarea>
                        </div>
                        
                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                Change Reason *
                            </label>
                            <textarea name="change_reason" 
                                      id="updateChangeReason"
                                      class="form-input w-full p-3 rounded-lg border"
                                      style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                      rows="2"
                                      placeholder="Why are you updating this agreement?"
                                      required></textarea>
                        </div>
                        
                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                Effective Date *
                            </label>
                            <input type="date" 
                                   name="effective_date" 
                                   id="updateEffectiveDate"
                                   class="form-input w-full p-3 rounded-lg border"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                   min="{{ now()->format('Y-m-d') }}"
                                   required>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex items-center">
                                <input type="checkbox" name="regenerate_pdf" value="1" class="form-checkbox mr-2" checked>
                                <span class="text-sm" style="color: var(--text-primary);">Regenerate PDF</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="resend_for_signature" value="1" class="form-checkbox mr-2">
                                <span class="text-sm" style="color: var(--text-primary);">Resend for Signature</span>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('updateAgreementModal')">
                    Cancel
                </button>
                <button type="submit" 
                        form="updateAgreementForm"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-paper-plane mr-2"></i> Update Agreement
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Terminate Agreement Modal -->
<div id="terminateModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm" onclick="closeModal('terminateModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container" style="background-color: var(--card-bg); border: 1px solid var(--border-color); max-width: 500px; width: 100%;">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-ban mr-2" style="color: var(--danger);"></i> Terminate Agreement
                </h3>
                <button type="button" onclick="closeModal('terminateModal')" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="terminateForm" method="POST" action="">
                    @csrf
                    <div class="space-y-4">
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-triangle mr-2 mt-0.5" style="color: var(--warning);"></i>
                                <div>
                                    <p class="font-bold" style="color: var(--warning);">Notice!</p>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                        Terminating this agreement will stop all future billing. This action can be reversed by creating a new agreement.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                Termination Reason *
                            </label>
                            <textarea name="termination_reason" 
                                      id="terminationReason"
                                      class="form-input w-full p-3 rounded-lg border"
                                      style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                      rows="3"
                                      placeholder="Please provide a reason for termination"
                                      required></textarea>
                        </div>
                        
                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                Effective Date *
                            </label>
                            <input type="date" 
                                   name="effective_date" 
                                   id="terminationDate"
                                   class="form-input w-full p-3 rounded-lg border"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                   min="{{ now()->format('Y-m-d') }}"
                                   required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('terminateModal')">
                    Cancel
                </button>
                <button type="submit" 
                        form="terminateForm"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background-color: var(--danger); border: 1px solid var(--danger);">
                    <i class="fas fa-ban mr-2"></i> Terminate Agreement
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Set as Primary Modal -->
<div id="setAsPrimaryModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm" onclick="closeModal('setAsPrimaryModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container" style="background-color: var(--card-bg); border: 1px solid var(--border-color); max-width: 500px; width: 100%;">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-crown mr-2" style="color: var(--primary);"></i> Set as Primary Billing Contact
                </h3>
                <button type="button" onclick="closeModal('setAsPrimaryModal')" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="setAsPrimaryForm" method="POST" action="">
                    @csrf
                    <div class="space-y-4">
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.1); border: 1px solid rgba(var(--primary-rgb), 0.2);">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--primary);"></i>
                                <div>
                                    <p class="font-bold" style="color: var(--primary);">Primary Billing Contact</p>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                        Setting this agreement as primary will make <strong id="primarySuperAdminName"></strong> the primary billing contact.
                                        All invoices will be sent to this super admin.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                Billing Contact Name
                            </label>
                            <input type="text" name="billing_contact_name" id="primaryBillingContactName"
                                   class="form-input w-full p-3 rounded-lg border"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                   placeholder="Leave empty to use super admin name">
                        </div>
                        
                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                Billing Contact Email
                            </label>
                            <input type="email" name="billing_contact_email" id="primaryBillingContactEmail"
                                   class="form-input w-full p-3 rounded-lg border"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                   placeholder="Leave empty to use super admin email">
                        </div>
                        
                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                Billing Contact Phone
                            </label>
                            <input type="tel" name="billing_contact_phone" id="primaryBillingContactPhone"
                                   class="form-input w-full p-3 rounded-lg border"
                                   style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                   placeholder="Leave empty to use super admin phone">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('setAsPrimaryModal')">
                    Cancel
                </button>
                <button type="submit" 
                        form="setAsPrimaryForm"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white"
                        style="background-color: var(--primary); border: 1px solid var(--primary);">
                    <i class="fas fa-crown mr-2"></i> Set as Primary
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Resend Signature Request Modal -->
<div id="resendSignatureModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm" onclick="closeModal('resendSignatureModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container" style="background-color: var(--card-bg); border: 1px solid var(--border-color); max-width: 500px; width: 100%;">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-envelope mr-2" style="color: var(--warning);"></i> Resend Signature Request
                </h3>
                <button type="button" onclick="closeModal('resendSignatureModal')" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="resendSignatureForm" method="POST" action="">
                    @csrf
                    <div class="space-y-4">
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.2);">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--info);"></i>
                                <div>
                                    <p class="font-bold" style="color: var(--info);">Resend Signature Request</p>
                                    <p class="text-sm mt-1" style="color: var(--text-secondary);">
                                        You have already signed this agreement. Resend the signature request to the super admin.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                                Additional Message (Optional)
                            </label>
                            <textarea name="message" 
                                      id="resendSignatureMessage"
                                      class="form-input w-full p-3 rounded-lg border"
                                      style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                      rows="3"
                                      placeholder="Add a personal message to the super admin..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" 
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('resendSignatureModal')">
                    Cancel
                </button>
                <button type="submit" 
                        form="resendSignatureForm"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-paper-plane mr-2"></i> Send Request
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     SEND REMINDER MODAL
     ============================================================ -->
<div id="sendReminderModal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm" onclick="closeModal('sendReminderModal')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="modal-container" style="background-color: var(--card-bg); border: 1px solid var(--border-color); max-width: 480px; width: 100%;">
            <div class="modal-header">
                <h3 class="flex items-center text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-bell mr-2" style="color: var(--secondary);"></i> Send Reminder
                </h3>
                <button type="button" onclick="closeModal('sendReminderModal')" class="modal-close-btn">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="space-y-4">
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--secondary-rgb), 0.1); border: 1px solid rgba(var(--secondary-rgb), 0.2);">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle mr-2 mt-0.5" style="color: var(--secondary);"></i>
                            <div>
                                <p class="text-sm" style="color: var(--text-secondary);">
                                    Send a reminder to the super admin for agreement
                                    <strong id="sendReminderAgreementNumber" style="color: var(--text-primary);"></strong>.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Reminder Type</label>
                        <select id="sendReminderType" class="form-select w-full p-2.5 rounded-lg border"
                                style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                            <option value="payment">Payment Reminder</option>
                            <option value="signature">Signature Reminder</option>
                        </select>
                    </div>

                    <div>
                        <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Additional Message (optional)</label>
                        <textarea id="sendReminderMessage" rows="3"
                                  class="form-input w-full p-3 rounded-lg border"
                                  style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                                  placeholder="Optional personal message to include with the reminder..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                        style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);"
                        onclick="closeModal('sendReminderModal')">
                    Cancel
                </button>
                <button type="button"
                        id="sendReminderConfirmBtn"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-paper-plane mr-2"></i> Send Reminder
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

@endsection

@push('scripts')
<script>
let currentAgreementId = null;
let currentAgreementNumber = null;

// Reminder state
let currentReminderAgreementId = null;
let currentReminderButton = null;

// Modal Functions
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function scrollToCreateForm() {
    const createForm = document.getElementById('createAgreementForm');
    if (createForm) {
        createForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        window.location.href = "{{ route('developer.billing.dashboard') }}";
    }
}

// Delete Agreement (Soft Delete)
function showDeleteAgreementModal(agreementId, agreementNumber) {
    currentAgreementId = agreementId;
    currentAgreementNumber = agreementNumber;
    
    const form = document.getElementById('deleteAgreementForm');
    form.action = `/developer/billing/agreements/${agreementId}/delete`;
    
    document.getElementById('deleteAgreementNumber').textContent = agreementNumber;
    
    const confirmationInput = document.getElementById('deleteConfirmationText');
    const confirmButton = document.getElementById('deleteConfirmButton');
    
    confirmationInput.value = '';
    confirmButton.disabled = true;
    confirmButton.style.opacity = '0.5';
    confirmButton.style.cursor = 'not-allowed';
    
    confirmationInput.oninput = function() {
        if (this.value === agreementNumber) {
            confirmButton.disabled = false;
            confirmButton.style.opacity = '1';
            confirmButton.style.cursor = 'pointer';
        } else {
            confirmButton.disabled = true;
            confirmButton.style.opacity = '0.5';
            confirmButton.style.cursor = 'not-allowed';
        }
    };
    
    openModal('deleteAgreementModal');
}

// Update Agreement
function showUpdateAgreementModal(agreementId) {
    fetch(`/developer/billing/agreements/${agreementId}/details`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const form = document.getElementById('updateAgreementForm');
                form.action = `/developer/billing/agreements/${agreementId}/update`;
                
                document.getElementById('updateAmount').value = data.agreement.amount;
                document.getElementById('updateDescription').value = data.agreement.description;
                document.getElementById('updateChangeReason').value = '';
                document.getElementById('updateEffectiveDate').value = new Date().toISOString().split('T')[0];
                
                openModal('updateAgreementModal');
            } else {
                showToast('Error fetching agreement details', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error fetching agreement details', 'error');
        });
}

// Record Payment
function recordPayment(agreementId) {
    window.location.href = `/developer/billing/agreements/${agreementId}/record-payment-form`;
}

// ============================================================
// Send Reminder — opens modal, then dispatches on confirm
// ============================================================
function sendReminder(agreementId, agreementNumber, buttonEl) {
    currentReminderAgreementId = agreementId;
    currentReminderButton = buttonEl || null;

    const numberEl = document.getElementById('sendReminderAgreementNumber');
    if (numberEl) numberEl.textContent = agreementNumber ? `#${agreementNumber}` : `#${agreementId}`;

    const typeEl = document.getElementById('sendReminderType');
    if (typeEl) typeEl.value = 'payment';

    const msgEl = document.getElementById('sendReminderMessage');
    if (msgEl) msgEl.value = '';

    openModal('sendReminderModal');
}

/**
 * Performs the actual POST to the backend.
 * Called by the Send Reminder modal confirm button.
 */
function dispatchReminder(agreementId, reminderType, message, buttonEl) {
    const originalHtml = buttonEl ? buttonEl.innerHTML : null;
    if (buttonEl) {
        buttonEl.disabled = true;
        buttonEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
        || '{{ csrf_token() }}';

    fetch(`/developer/billing/agreements/${agreementId}/send-reminder`, {
        method: 'POST',
        headers: {
            'Content-Type':     'application/json',
            'X-CSRF-TOKEN':     csrfToken,
            'Accept':           'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            reminder_type: reminderType,
            message:       message || null,
        }),
    })
    .then(async (response) => {
        let data = null;
        try {
            data = await response.json();
        } catch (e) {
            // Non-JSON response — fall through to error handling
        }

        if (!response.ok) {
            throw new Error(data?.message
                || `Server returned ${response.status} ${response.statusText}`);
        }

        if (!data || data.success !== true) {
            throw new Error(data?.message || 'Unknown error');
        }

        return data;
    })
    .then((data) => {
        const typeLabel = reminderType === 'signature' ? 'Signature' : 'Payment';
        showToast(
            data.message || `${typeLabel} reminder sent successfully!`,
            'success'
        );
    })
    .catch((error) => {
        console.error('Send reminder error:', error);
        showToast('Failed to send reminder: ' + error.message, 'error');
    })
    .finally(() => {
        if (buttonEl) {
            buttonEl.disabled = false;
            buttonEl.innerHTML = originalHtml;
        }
    });
}

// Resend Signature Request
function showResendSignatureModal(agreementId) {
    const form = document.getElementById('resendSignatureForm');
    form.action = `/developer/billing/agreements/${agreementId}/send-for-signing`;
    document.getElementById('resendSignatureMessage').value = '';
    openModal('resendSignatureModal');
}

// Terminate Agreement
function showTerminateModal(agreementId) {
    const form = document.getElementById('terminateForm');
    form.action = `/developer/billing/agreements/${agreementId}/terminate`;
    
    document.getElementById('terminationReason').value = '';
    document.getElementById('terminationDate').value = new Date().toISOString().split('T')[0];
    
    openModal('terminateModal');
}

// Set as Primary
function showSetAsPrimaryModal(agreementId, agreementNumber, superAdminName, superAdminEmail) {
    const form = document.getElementById('setAsPrimaryForm');
    form.action = `/developer/billing/agreements/${agreementId}/set-primary`;
    
    document.getElementById('primarySuperAdminName').textContent = superAdminName;
    document.getElementById('primaryBillingContactName').value = '';
    document.getElementById('primaryBillingContactEmail').value = superAdminEmail;
    document.getElementById('primaryBillingContactPhone').value = '';
    
    openModal('setAsPrimaryModal');
}

// Export Agreements
function exportAgreements() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'agreements');
    window.location.href = '/developer/billing/export?' + params.toString();
}

// Update Per Page
function updatePerPage(perPage) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', perPage);
    window.location.href = url.toString();
}

// Toggle Custom Dates
function toggleCustomDates(value) {
    const customDateRange = document.getElementById('customDateRange');
    if (customDateRange) {
        if (value === 'custom') {
            customDateRange.classList.remove('hidden');
        } else {
            customDateRange.classList.add('hidden');
        }
    }
}

// Toast Notification
function showToast(message, type = 'info') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-green-100 dark:bg-green-900' : 
                    type === 'error' ? 'bg-red-100 dark:bg-red-900' :
                    type === 'warning' ? 'bg-yellow-100 dark:bg-yellow-900' : 
                    'bg-blue-100 dark:bg-blue-900';
    const textColor = type === 'success' ? 'text-green-800 dark:text-green-200' :
                      type === 'error' ? 'text-red-800 dark:text-red-200' :
                      type === 'warning' ? 'text-yellow-800 dark:text-yellow-200' :
                      'text-blue-800 dark:text-blue-200';
    
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-[300px] max-w-md transform transition-all duration-300 translate-x-full ${bgColor} ${textColor}`;
    
    const icon = type === 'success' ? 'fa-check-circle' :
                 type === 'error' ? 'fa-exclamation-circle' :
                 type === 'warning' ? 'fa-exclamation-triangle' :
                 'fa-info-circle';
    
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${icon} mr-3 text-lg"></i>
            <span class="text-sm font-medium">${message}</span>
        </div>
        <button class="ml-4 hover:opacity-70 transition-opacity" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);
    
    setTimeout(() => {
        if (toast.parentNode) {
            toast.classList.remove('translate-x-0');
            toast.classList.add('translate-x-full');
            setTimeout(() => {
                if (toast.parentNode) toast.remove();
            }, 300);
        }
    }, 5000);
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Initialize custom date range visibility
    const dateRangeSelect = document.getElementById('date_range');
    if (dateRangeSelect) {
        toggleCustomDates(dateRangeSelect.value);
        dateRangeSelect.addEventListener('change', function() {
            toggleCustomDates(this.value);
        });
    }

    // Wire up Send Reminder modal confirm button
    const sendReminderConfirmBtn = document.getElementById('sendReminderConfirmBtn');
    if (sendReminderConfirmBtn) {
        sendReminderConfirmBtn.addEventListener('click', function () {
            const typeEl = document.getElementById('sendReminderType');
            const msgEl  = document.getElementById('sendReminderMessage');

            const reminderType = typeEl ? typeEl.value : 'payment';
            const message      = msgEl  ? msgEl.value.trim() : '';

            closeModal('sendReminderModal');

            dispatchReminder(
                currentReminderAgreementId,
                reminderType,
                message,
                currentReminderButton
            );

            // Reset state
            currentReminderAgreementId = null;
            currentReminderButton = null;
        });
    }
    
    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal('deleteAgreementModal');
            closeModal('updateAgreementModal');
            closeModal('terminateModal');
            closeModal('setAsPrimaryModal');
            closeModal('resendSignatureModal');
            closeModal('sendReminderModal');
        }
    });
    
    // Auto-submit filters on per_page change
    const perPageSelect = document.querySelector('select[name="per_page"]');
    if (perPageSelect) {
        perPageSelect.addEventListener('change', function() {
            document.getElementById('filterForm')?.submit();
        });
    }
    
    // Show success/error messages from session
    @if(session('success'))
        showToast("{{ session('success') }}", 'success');
    @endif
    
    @if(session('error'))
        showToast("{{ session('error') }}", 'error');
    @endif
    
    @if(session('warning'))
        showToast("{{ session('warning') }}", 'warning');
    @endif
});
</script>
@endpush

@push('styles')
<style>
.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.action-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

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

.modal-container {
    animation: modalFadeIn 0.3s ease-out;
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

.stat-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Badge styles */
.badge-success {
    background-color: rgba(var(--success-rgb), 0.1) !important;
    color: var(--success) !important;
    border: 1px solid rgba(var(--success-rgb), 0.3) !important;
}

.badge-warning {
    background-color: rgba(var(--warning-rgb), 0.1) !important;
    color: var(--warning) !important;
    border: 1px solid rgba(var(--warning-rgb), 0.3) !important;
}

.badge-danger {
    background-color: rgba(var(--danger-rgb), 0.1) !important;
    color: var(--danger) !important;
    border: 1px solid rgba(var(--danger-rgb), 0.3) !important;
}

.badge-info {
    background-color: rgba(var(--info-rgb), 0.1) !important;
    color: var(--info) !important;
    border: 1px solid rgba(var(--info-rgb), 0.3) !important;
}

.badge-primary {
    background-color: rgba(var(--primary-rgb), 0.1) !important;
    color: var(--primary) !important;
    border: 1px solid rgba(var(--primary-rgb), 0.3) !important;
}

.badge-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}

/* Form elements */
.form-input, .form-select, .form-checkbox {
    background-color: var(--card-bg) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px !important;
    transition: all 0.2s ease !important;
}

.form-input:focus, .form-select:focus {
    outline: none !important;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1) !important;
}

/* Responsive */
@media (max-width: 768px) {
    .grid-cols-1.sm\:grid-cols-2.lg\:grid-cols-5 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .modal-container {
        margin: 1rem;
        width: calc(100% - 2rem);
    }
    
    .action-btn {
        width: 28px;
        height: 28px;
    }
}

@media (max-width: 640px) {
    .grid-cols-1.md\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .modal-footer {
        flex-direction: column;
    }
    
    .modal-footer button {
        width: 100%;
    }
}
</style>
@endpush