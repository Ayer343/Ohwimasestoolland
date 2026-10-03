{{-- resources/views/superadmin/billing/agreements-list.blade.php --}}
@extends('layouts.app')

@php
    $pageTitle = 'Super Admin Agreements List';
    
    function formatCurrency($amount, $currency = 'GHS') {
        if (empty($amount)) return 'GH₵0.00';
        if ($currency === 'GHS') {
            return 'GH₵' . number_format($amount, 2);
        }
        return $currency . ' ' . number_format($amount, 2);
    }
    
    // Status colors
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
    
    // Payment status colors
    function getPaymentStatusColor($status) {
        $colors = [
            'paid' => 'success',
            'partial' => 'warning',
            'unpaid' => 'danger',
            'overdue' => 'danger'
        ];
        return $colors[$status] ?? 'secondary';
    }
    
    // Theme detection
    $isDarkMode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] === 'true' : false;
@endphp

@section('title', $pageTitle)

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
                        My Billing Agreements
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>View and manage all your billing agreements</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-circle mr-1" style="color: var(--success);"></i>
                        <span class="font-medium">{{ $agreements->total() }} total agreements</span>
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ now()->format('F j, Y') }}
                <div class="flex items-center space-x-2 mt-2">
                    <a href="{{ route('superadmin.billing.dashboard') }}" 
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                       style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-arrow-left mr-1"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Card -->
    <div class="card p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('superadmin.dashboard') }}" 
                   class="inline-flex items-center text-sm font-medium" 
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Main Dashboard
                </a>
                
                <a href="{{ route('superadmin.billing.dashboard') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-chart-line mr-1"></i> Billing Dashboard
                </a>
                
                <a href="{{ route('superadmin.billing.reports') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-chart-bar mr-1"></i> Reports
                </a>
                
                <a href="{{ route('superadmin.billing.history') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-history mr-1"></i> Payment History
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="card stat-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-handshake text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Active Agreements</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $stats['active_agreements'] }}</p>
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ $stats['active_agreements'] > 0 ? 'success' : 'danger' }}">
                        <i class="fas fa-{{ $stats['active_agreements'] > 0 ? 'check' : 'times' }} mr-1"></i>
                        {{ $stats['active_agreements'] > 0 ? 'Active' : 'None' }}
                    </span>
                </div>
            </div>
        </div>
        
        <div class="card stat-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-chart-bar text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Agreed</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ formatCurrency($stats['total_amount_agreed']) }}</p>
                </div>
            </div>
        </div>
        
        <div class="card stat-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-money-check-alt text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Paid</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ formatCurrency($stats['total_amount_paid']) }}</p>
                </div>
            </div>
        </div>
        
        <div class="card stat-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-percentage text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Payment Progress</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        @if($stats['total_amount_agreed'] > 0)
                            {{ round(($stats['total_amount_paid'] / $stats['total_amount_agreed']) * 100, 1) }}%
                        @else
                            0%
                        @endif
                    </p>
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ $stats['total_amount_paid'] >= $stats['total_amount_agreed'] ? 'success' : ($stats['total_amount_paid'] > 0 ? 'warning' : 'danger') }}">
                        <i class="fas fa-chart-{{ $stats['total_amount_paid'] >= $stats['total_amount_agreed'] ? 'line' : 'bar' }} mr-1"></i>
                        {{ $stats['total_amount_paid'] >= $stats['total_amount_agreed'] ? 'Complete' : ($stats['total_amount_paid'] > 0 ? 'Partial' : 'None') }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filter Agreements
        </h3>
        
        <form method="GET" action="{{ route('superadmin.billing.agreements-list') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Status Filter -->
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        Status
                    </label>
                    <select name="status" class="form-select w-full p-3 rounded-lg border"
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                        <option value="all" {{ request('status') == 'all' ? 'selected' : '' }}>All Statuses</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="terminated" {{ request('status') == 'terminated' ? 'selected' : '' }}>Terminated</option>
                    </select>
                </div>
                
                <!-- Payment Status Filter -->
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        Payment Status
                    </label>
                    <select name="payment_status" class="form-select w-full p-3 rounded-lg border"
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                        <option value="all" {{ request('payment_status') == 'all' ? 'selected' : '' }}>All Payment Statuses</option>
                        <option value="paid" {{ request('payment_status') == 'paid' ? 'selected' : '' }}>Paid</option>
                        <option value="partial" {{ request('payment_status') == 'partial' ? 'selected' : '' }}>Partial</option>
                        <option value="unpaid" {{ request('payment_status') == 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                        <option value="overdue" {{ request('payment_status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                    </select>
                </div>
                
                <!-- Frequency Filter -->
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        Billing Frequency
                    </label>
                    <select name="frequency" class="form-select w-full p-3 rounded-lg border"
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                        <option value="all" {{ request('frequency') == 'all' ? 'selected' : '' }}>All Frequencies</option>
                        <option value="monthly" {{ request('frequency') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                        <option value="quarterly" {{ request('frequency') == 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                        <option value="yearly" {{ request('frequency') == 'yearly' ? 'selected' : '' }}>Yearly</option>
                        <option value="one_time" {{ request('frequency') == 'one_time' ? 'selected' : '' }}>One Time</option>
                    </select>
                </div>
                
                <!-- Date Range -->
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        Date Range
                    </label>
                    <select name="date_range" class="form-select w-full p-3 rounded-lg border"
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                            onchange="toggleCustomDates(this.value)">
                        <option value="all" {{ request('date_range') == 'all' ? 'selected' : '' }}>All Time</option>
                        <option value="this_month" {{ request('date_range') == 'this_month' ? 'selected' : '' }}>This Month</option>
                        <option value="last_month" {{ request('date_range') == 'last_month' ? 'selected' : '' }}>Last Month</option>
                        <option value="this_year" {{ request('date_range') == 'this_year' ? 'selected' : '' }}>This Year</option>
                        <option value="custom" {{ request('date_range') == 'custom' ? 'selected' : '' }}>Custom Range</option>
                    </select>
                </div>
            </div>
            
            <!-- Custom Date Range -->
            <div id="customDateRange" class="grid grid-cols-1 md:grid-cols-2 gap-4 {{ request('date_range') != 'custom' ? 'hidden' : '' }}">
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        Start Date
                    </label>
                    <input type="date" 
                           name="start_date" 
                           value="{{ request('start_date') }}" 
                           class="form-input w-full p-3 rounded-lg border"
                           style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        End Date
                    </label>
                    <input type="date" 
                           name="end_date" 
                           value="{{ request('end_date') }}" 
                           class="form-input w-full p-3 rounded-lg border"
                           style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                </div>
            </div>
            
            <!-- Search -->
            <div>
                <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                    Search
                </label>
                <div class="relative">
                    <input type="text" 
                           name="search" 
                           value="{{ request('search') }}" 
                           class="form-input w-full p-3 rounded-lg border pl-10"
                           style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                           placeholder="Search by agreement number, description, or developer">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search" style="color: var(--text-secondary);"></i>
                    </div>
                </div>
            </div>
            
            <!-- Filter Buttons -->
            <div class="flex justify-between items-center pt-4" style="border-top: 1px solid var(--border-color);">
                <div>
                    <a href="{{ route('superadmin.billing.agreements-list') }}" 
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-redo mr-2"></i> Reset Filters
                    </a>
                </div>
                <div class="flex space-x-2">
                    <button type="button" 
                            onclick="exportAgreements()" 
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                            style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <i class="fas fa-download mr-2"></i> Export
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Agreements Table -->
    <div class="card p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-table mr-2" style="color: var(--primary);"></i> Agreements List
            </h3>
            <div class="text-sm" style="color: var(--text-secondary);">
                Showing {{ $agreements->firstItem() }} - {{ $agreements->lastItem() }} of {{ $agreements->total() }}
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Agreement</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Developer</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Amount</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Frequency</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Status</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Payment Status</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Start Date</th>
                        <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($agreements as $agreement)
                    @php
                        // Check if super admin has signed this agreement
                        $hasSigned = $agreement->signatures 
                            ? $agreement->signatures->where('signature_type', 'super_admin')->isNotEmpty() 
                            : false;
                        
                        // Determine if agreement is ready for super admin signature
                        $canSign = $agreement->status == 'pending' && !$hasSigned && $agreement->agreement_pdf_path;
                        
                        // Determine if signed PDF is available for download
                        $hasSignedPdf = $agreement->status == 'active' && $agreement->signed_agreement_pdf_path;
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <div>
                                <div class="font-mono text-sm font-medium" style="color: var(--text-primary);">
                                    @if($agreement->is_primary_for_billing)
                                    <i class="fas fa-star text-xs mr-1" style="color: var(--success);" title="Primary Billing Agreement"></i>
                                    @endif
                                    {{ $agreement->agreement_number }}
                                </div>
                                <div class="text-xs truncate max-w-xs" style="color: var(--text-secondary);">
                                    {{ Str::limit($agreement->description, 50) }}
                                </div>
                            </div>
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2"
                                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                    <i class="fas fa-code text-xs"></i>
                                </div>
                                <div>
                                    <div style="color: var(--text-primary);">{{ $agreement->developerSetting->developer_name ?? 'Developer' }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">{{ $agreement->developerSetting->developer_email ?? '' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <div class="font-medium" style="color: var(--text-primary);">{{ formatCurrency($agreement->amount, $agreement->currency) }}</div>
                            <div class="text-xs {{ $agreement->amount_received >= $agreement->amount ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400' }}">
                                Paid: {{ formatCurrency($agreement->amount_received, $agreement->currency) }}
                            </div>
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ $agreement->billing_frequency == 'one_time' ? 'warning' : ($agreement->billing_frequency == 'yearly' ? 'info' : 'primary') }}">
                                {{ ucfirst($agreement->billing_frequency) }}
                            </span>
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ getStatusColor($agreement->status) }}">
                                <i class="fas fa-circle mr-1" style="font-size: 0.5rem;"></i>
                                {{ ucfirst($agreement->status) }}
                            </span>
                            @if($agreement->status === 'rejected' && $agreement->rejection_reason)
                            <div class="text-xs mt-1 text-danger" title="{{ $agreement->rejection_reason }}">
                                <i class="fas fa-comment mr-1"></i> Rejected
                            </div>
                            @endif
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-{{ getPaymentStatusColor($agreement->payment_status) }}">
                                <i class="fas fa-{{ $agreement->payment_status == 'paid' ? 'check' : ($agreement->payment_status == 'partial' ? 'exclamation' : 'times') }} mr-1"></i>
                                {{ ucfirst($agreement->payment_status) }}
                            </span>
                            @if($agreement->payment_status === 'partial')
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">
                                {{ formatCurrency($agreement->amount_received) }} / {{ formatCurrency($agreement->amount) }}
                            </div>
                            @endif
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <div style="color: var(--text-secondary);">{{ \Carbon\Carbon::parse($agreement->start_date)->format('M d, Y') }}</div>
                        </td>
                        <td class="p-3 border-b" style="border-color: var(--border-color);">
                            <div class="flex flex-wrap gap-1">
                                <!-- View Details Button -->
                                <a href="{{ route('superadmin.billing.view-agreement', $agreement->id) }}" 
                                   class="px-2 py-1 rounded text-xs font-medium inline-flex items-center"
                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);"
                                   title="View Details">
                                    <i class="fas fa-eye text-xs mr-1"></i> View
                                </a>
                                
                                <!-- Sign Agreement Button (only if pending and not signed) -->
                                @if($canSign)
                                <a href="{{ route('superadmin.billing.view-agreement-signing', $agreement->id) }}" 
                                   class="px-2 py-1 rounded text-xs font-medium inline-flex items-center"
                                   style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); border: 1px solid rgba(var(--warning-rgb), 0.3);"
                                   title="Sign Agreement">
                                    <i class="fas fa-signature text-xs mr-1"></i> Sign
                                </a>
                                @endif
                                
                                <!-- Download Signed PDF Button (only if active and signed PDF exists) -->
                                @if($hasSignedPdf)
                                <a href="{{ route('superadmin.billing.download-signed-agreement', $agreement->id) }}" 
                                   class="px-2 py-1 rounded text-xs font-medium inline-flex items-center"
                                   style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);"
                                   title="Download Signed Agreement">
                                    <i class="fas fa-download text-xs mr-1"></i> PDF
                                </a>
                                @endif
                                
                                <!-- Record Payment Button - REMOVED (Super Admin should NOT record payments) -->
                                <!-- Payment recording is handled by the Developer only -->
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="p-8 text-center" style="border-color: var(--border-color);">
                            <i class="fas fa-inbox text-3xl mb-2" style="color: var(--text-secondary);"></i>
                            <p style="color: var(--text-secondary);">No agreements found</p>
                            @if(request()->hasAny(['status', 'payment_status', 'frequency', 'search', 'date_range']))
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">Try adjusting your filters</p>
                            <a href="{{ route('superadmin.billing.agreements-list') }}" 
                               class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center mt-2"
                               style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                                <i class="fas fa-redo mr-1"></i> Clear Filters
                            </a>
                            @else
                            <p class="text-sm mt-1" style="color: var(--text-secondary);">You don't have any billing agreements yet</p>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        @if($agreements->hasPages())
        <div class="mt-6 pt-6" style="border-top: 1px solid var(--border-color);">
            <div class="flex flex-col md:flex-row items-center justify-between">
                <div class="text-sm mb-4 md:mb-0" style="color: var(--text-secondary);">
                    Showing {{ $agreements->firstItem() }} - {{ $agreements->lastItem() }} of {{ $agreements->total() }}
                </div>
                <div class="flex items-center space-x-2">
                    @if($agreements->onFirstPage())
                    <span class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center opacity-50 cursor-not-allowed"
                          style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-chevron-left mr-1"></i> Previous
                    </span>
                    @else
                    <a href="{{ $agreements->previousPageUrl() }}" 
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-chevron-left mr-1"></i> Previous
                    </a>
                    @endif
                    
                    <div class="hidden md:flex items-center space-x-1">
                        @foreach($agreements->getUrlRange(1, $agreements->lastPage()) as $page => $url)
                            @if($page == $agreements->currentPage())
                            <span class="w-8 h-8 flex items-center justify-center text-sm font-medium btn-primary text-white rounded-full">
                                {{ $page }}
                            </span>
                            @else
                            <a href="{{ $url }}" 
                               class="w-8 h-8 flex items-center justify-center border rounded-full text-sm font-medium"
                               style="color: var(--text-secondary); border-color: var(--border-color);">
                                {{ $page }}
                            </a>
                            @endif
                        @endforeach
                    </div>
                    
                    <div class="md:hidden text-sm font-medium" style="color: var(--text-primary);">
                        Page {{ $agreements->currentPage() }} of {{ $agreements->lastPage() }}
                    </div>
                    
                    @if($agreements->hasMorePages())
                    <a href="{{ $agreements->nextPageUrl() }}" 
                       class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        Next <i class="fas fa-chevron-right ml-1"></i>
                    </a>
                    @else
                    <span class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center opacity-50 cursor-not-allowed"
                          style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        Next <i class="fas fa-chevron-right ml-1"></i>
                    </span>
                    @endif
                    
                    <select onchange="updatePerPage(this.value)" 
                            class="form-select p-2 text-sm rounded-lg border"
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                        <option value="10" {{ request('per_page', 20) == 10 ? 'selected' : '' }}>10 per page</option>
                        <option value="20" {{ request('per_page', 20) == 20 ? 'selected' : '' }}>20 per page</option>
                        <option value="50" {{ request('per_page', 20) == 50 ? 'selected' : '' }}>50 per page</option>
                        <option value="100" {{ request('per_page', 20) == 100 ? 'selected' : '' }}>100 per page</option>
                    </select>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@section('scripts')
<script>
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

function exportAgreements() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'agreements');
    showToast('Preparing export...', 'info');
    
    const overlay = document.createElement('div');
    overlay.id = 'exportOverlay';
    overlay.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50';
    overlay.innerHTML = '<div class="bg-white dark:bg-gray-800 p-6 rounded-lg"><i class="fas fa-spinner fa-spin mr-2"></i> Preparing export...</div>';
    document.body.appendChild(overlay);
    
    setTimeout(() => {
        window.location.href = '/superadmin/billing/export?' + params.toString();
        document.getElementById('exportOverlay')?.remove();
    }, 1000);
}

function updatePerPage(perPage) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', perPage);
    window.location.href = url.toString();
}

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
    
    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.textContent = message;
    
    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 transition-colors duration-200';
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.onclick = () => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    };
    
    toast.appendChild(messageEl);
    toast.appendChild(closeBtn);
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

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    const dateRangeSelect = document.querySelector('select[name="date_range"]');
    if (dateRangeSelect) {
        toggleCustomDates(dateRangeSelect.value);
    }
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (!modal.classList.contains('hidden')) {
                    modal.classList.add('hidden');
                }
            });
        }
    });
    
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

<style>
/* Button styles */
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

/* Card styles */
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

.stat-card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    padding: 1.5rem !important;
    transition: transform 0.2s ease, box-shadow 0.2s ease !important;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
}

/* Form elements */
.form-input, .form-select {
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

/* Table styles */
.table {
    width: 100%;
    border-collapse: collapse;
}

.table tr:hover {
    background-color: rgba(var(--primary-rgb), 0.02) !important;
}

/* Animations */
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.fa-spinner.fa-spin {
    animation: spin 1s linear infinite;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

#exportOverlay {
    animation: fadeIn 0.3s ease-out;
}

/* Responsive */
@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
    
    .table {
        font-size: 0.75rem;
    }
    
    .table th,
    .table td {
        padding: 0.5rem !important;
    }
}

@media (max-width: 640px) {
    .card .p-6 {
        padding: 1rem !important;
    }
    
    .text-2xl {
        font-size: 1.25rem !important;
    }
}

/* Scrollbar */
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
</style>
@endsection