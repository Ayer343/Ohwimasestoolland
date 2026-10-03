{{-- resources/views/developer/billing/superadmin-agreements.blade.php --}}
@extends('layouts.dev')

@php
    $pageTitle = 'My Billing Agreements';
    $user = auth()->user();
    
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
@endphp

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2 header-icon">
                        <i class="fas fa-file-contract text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center header-title">
                        <i class="fas fa-file-contract mr-2"></i> 
                        My Billing Agreements
                    </h2>
                    <div class="text-sm flex items-center mt-1 header-subtitle">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>View and manage your billing agreements with super admins</span>
                        <span class="mx-2">•</span>
                        <span class="font-medium">{{ $agreements->total() }} total agreements</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center space-x-3 header-info">
                <a href="{{ route('developer.billing.dashboard') }}" class="btn-secondary-small">
                    <i class="fas fa-arrow-left mr-1"></i> Dashboard
                </a>
                <a href="{{ route('developer.billing.agreements-list') }}" class="btn-primary-small">
                    <i class="fas fa-list mr-1"></i> All Agreements
                </a>
                <button onclick="exportAgreements()" class="btn-success-small">
                    <i class="fas fa-download mr-1"></i> Export
                </button>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="card stat-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center stat-icon-success">
                        <i class="fas fa-handshake text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="stat-label">Active Agreements</p>
                    <p class="stat-value">{{ $stats['active_agreements'] ?? 0 }}</p>
                </div>
            </div>
        </div>
        
        <div class="card stat-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center stat-icon-primary">
                        <i class="fas fa-chart-bar text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="stat-label">Total Agreed</p>
                    <p class="stat-value">{{ formatCurrency($stats['total_amount_agreed'] ?? 0) }}</p>
                </div>
            </div>
        </div>
        
        <div class="card stat-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center stat-icon-success">
                        <i class="fas fa-money-check-alt text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="stat-label">Total Paid</p>
                    <p class="stat-value">{{ formatCurrency($stats['total_amount_received'] ?? 0) }}</p>
                </div>
            </div>
        </div>
        
        <div class="card stat-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center stat-icon-warning">
                        <i class="fas fa-percentage text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="stat-label">Payment Rate</p>
                    <p class="stat-value">
                        @if(($stats['total_amount_agreed'] ?? 0) > 0)
                            {{ round((($stats['total_amount_received'] ?? 0) / ($stats['total_amount_agreed'] ?? 1)) * 100, 1) }}%
                        @else
                            0%
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- NEW: Signature Status Summary Card -->
    <div class="card mb-6">
        <div class="p-6">
            <h3 class="section-title">
                <i class="fas fa-signature mr-2"></i> Signature Status Overview
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-clock text-warning text-xl mb-2"></i>
                    <p class="text-sm text-secondary">Awaiting Signature</p>
                    <p class="text-2xl font-bold text-warning">{{ $stats['awaiting_signature'] ?? 0 }}</p>
                </div>
                <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-check-circle text-success text-xl mb-2"></i>
                    <p class="text-sm text-secondary">Recently Signed</p>
                    <p class="text-2xl font-bold text-success">{{ $stats['recently_signed'] ?? 0 }}</p>
                </div>
                <div class="p-3 rounded-lg text-center" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-file-signature text-info text-xl mb-2"></i>
                    <p class="text-sm text-secondary">Total Agreements</p>
                    <p class="text-2xl font-bold text-info">{{ $stats['total_agreements'] ?? 0 }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card mb-6">
        <div class="p-6">
            <h3 class="section-title">
                <i class="fas fa-filter mr-2"></i> Filter Agreements
            </h3>
            
            <form method="GET" action="{{ route('developer.billing.agreements-list') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <!-- Status Filter -->
                    <div>
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
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
                        <label class="form-label">Payment Status</label>
                        <select name="payment_status" class="form-select">
                            <option value="all" {{ request('payment_status') == 'all' ? 'selected' : '' }}>All Payment Statuses</option>
                            <option value="paid" {{ request('payment_status') == 'paid' ? 'selected' : '' }}>Paid</option>
                            <option value="partial" {{ request('payment_status') == 'partial' ? 'selected' : '' }}>Partial</option>
                            <option value="unpaid" {{ request('payment_status') == 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                            <option value="overdue" {{ request('payment_status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                        </select>
                    </div>
                    
                    <!-- Frequency Filter -->
                    <div>
                        <label class="form-label">Billing Frequency</label>
                        <select name="frequency" class="form-select">
                            <option value="all" {{ request('frequency') == 'all' ? 'selected' : '' }}>All Frequencies</option>
                            <option value="monthly" {{ request('frequency') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                            <option value="quarterly" {{ request('frequency') == 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                            <option value="yearly" {{ request('frequency') == 'yearly' ? 'selected' : '' }}>Yearly</option>
                            <option value="one_time" {{ request('frequency') == 'one_time' ? 'selected' : '' }}>One Time</option>
                        </select>
                    </div>
                    
                    <!-- Date Range -->
                    <div>
                        <label class="form-label">Date Range</label>
                        <select name="date_range" class="form-select" onchange="toggleCustomDates(this.value)">
                            <option value="all" {{ request('date_range') == 'all' ? 'selected' : '' }}>All Time</option>
                            <option value="today" {{ request('date_range') == 'today' ? 'selected' : '' }}>Today</option>
                            <option value="this_week" {{ request('date_range') == 'this_week' ? 'selected' : '' }}>This Week</option>
                            <option value="this_month" {{ request('date_range') == 'this_month' ? 'selected' : '' }}>This Month</option>
                            <option value="last_month" {{ request('date_range') == 'last_month' ? 'selected' : '' }}>Last Month</option>
                            <option value="this_year" {{ request('date_range') == 'this_year' ? 'selected' : '' }}>This Year</option>
                            <option value="custom" {{ request('date_range') == 'custom' ? 'selected' : '' }}>Custom Range</option>
                        </select>
                    </div>
                    
                    <!-- Signature Status Filter (NEW) -->
                    <div>
                        <label class="form-label">Signature Status</label>
                        <select name="signature_status" class="form-select" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="signed" {{ request('signature_status') == 'signed' ? 'selected' : '' }}>Signed</option>
                            <option value="pending" {{ request('signature_status') == 'pending' ? 'selected' : '' }}>Pending Signature</option>
                            <option value="partial" {{ request('signature_status') == 'partial' ? 'selected' : '' }}>Partially Signed</option>
                        </select>
                    </div>
                </div>
                
                <!-- Custom Date Range -->
                <div id="customDateRange" class="grid grid-cols-1 md:grid-cols-2 gap-4 {{ request('date_range') != 'custom' ? 'hidden' : '' }}">
                    <div>
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" value="{{ request('start_date') }}" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" value="{{ request('end_date') }}" class="form-input">
                    </div>
                </div>
                
                <!-- Search -->
                <div>
                    <label class="form-label">Search</label>
                    <div class="relative">
                        <input type="text" name="search" value="{{ request('search') }}" class="form-input pl-10" placeholder="Search by agreement number, super admin name, or description">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-secondary"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Buttons -->
                <div class="flex justify-between items-center pt-4 border-t section-divider">
                    <div>
                        <a href="{{ route('developer.billing.agreements-list') }}" class="btn btn-secondary">
                            <i class="fas fa-redo mr-2"></i> Reset Filters
                        </a>
                    </div>
                    <div class="flex space-x-2">
                        <button type="button" onclick="exportAgreements()" class="btn btn-success">
                            <i class="fas fa-download mr-2"></i> Export CSV
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter mr-2"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Agreements Table -->
    <div class="card">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="section-title">
                    <i class="fas fa-table mr-2"></i> My Agreements
                </h3>
                <div class="text-sm text-secondary">
                    Showing {{ $agreements->firstItem() }} - {{ $agreements->lastItem() }} of {{ $agreements->total() }}
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="table-header">Agreement</th>
                            <th class="table-header">Super Admin</th>
                            <th class="table-header">Amount</th>
                            <th class="table-header">Frequency</th>
                            <th class="table-header">Start Date</th>
                            <th class="table-header">Status</th>
                            <th class="table-header">Payment Status</th>
                            <th class="table-header">Signatures</th>
                            <th class="table-header">Description</th>
                            <th class="table-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($agreements as $agreement)
                        <tr class="hover:bg-secondary/5 transition-colors">
                            <td class="table-cell">
                                <div class="font-mono text-sm">{{ $agreement->agreement_number }}</div>
                                <div class="text-xs text-secondary">
                                    {{ \Carbon\Carbon::parse($agreement->created_at)->format('M d, Y') }}
                                </div>
                            </td>
                            <td class="table-cell">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2" style="background-color: rgba(var(--primary-rgb), 0.1);">
                                        <i class="fas fa-user-shield text-xs text-primary"></i>
                                    </div>
                                    <div>
                                        <div class="text-sm">{{ $agreement->superAdmin->name ?? 'N/A' }}</div>
                                        <div class="text-xs text-secondary">{{ $agreement->superAdmin->email ?? '' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="table-cell table-cell-primary">
                                <div>{{ formatCurrency($agreement->amount, $agreement->currency) }}</div>
                                <div class="text-xs {{ $agreement->amount_received >= $agreement->amount ? 'text-success' : 'text-warning' }}">
                                    Paid: {{ formatCurrency($agreement->amount_received, $agreement->currency) }}
                                </div>
                            </td>
                            <td class="table-cell table-cell-secondary">
                                <span class="badge badge-info">
                                    {{ ucfirst($agreement->billing_frequency) }}
                                </span>
                            </td>
                            <td class="table-cell table-cell-secondary">
                                {{ \Carbon\Carbon::parse($agreement->start_date)->format('M d, Y') }}
                            </td>
                            <td class="table-cell">
                                <span class="badge badge-{{ getStatusColor($agreement->status) }}">
                                    {{ ucfirst($agreement->status) }}
                                </span>
                            </td>
                            <td class="table-cell">
                                <span class="badge badge-{{ getPaymentStatusColor($agreement->payment_status) }}">
                                    {{ ucfirst($agreement->payment_status) }}
                                </span>
                            </td>
                            <td class="table-cell">
                                @php
                                    $developerSigned = $agreement->signatures->where('signature_type', 'developer')->isNotEmpty();
                                    $superAdminSigned = $agreement->signatures->where('signature_type', 'super_admin')->isNotEmpty();
                                @endphp
                                <div class="flex flex-col space-y-1">
                                    @if($developerSigned)
                                        <span class="inline-flex items-center text-xs text-success">
                                            <i class="fas fa-check-circle mr-1"></i> You Signed
                                        </span>
                                    @elseif($agreement->status == 'pending')
                                        <span class="inline-flex items-center text-xs text-warning">
                                            <i class="fas fa-clock mr-1"></i> Awaiting Your Signature
                                        </span>
                                    @endif
                                    @if($superAdminSigned)
                                        <span class="inline-flex items-center text-xs text-success">
                                            <i class="fas fa-check-circle mr-1"></i> SA Signed
                                        </span>
                                    @endif
                                    @if($developerSigned && $superAdminSigned)
                                        <span class="inline-flex items-center text-xs text-success font-bold">
                                            <i class="fas fa-check-double mr-1"></i> Fully Executed
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="table-cell table-cell-secondary">
                                <div class="truncate max-w-xs" title="{{ $agreement->description }}">
                                    {{ Str::limit($agreement->description, 50) }}
                                </div>
                            </td>
                            <td class="table-cell">
                                <div class="flex flex-wrap gap-1">
                                    <!-- View Details -->
                                    <a href="{{ route('developer.billing.view-agreement', $agreement->id) }}" 
                                       class="btn btn-info btn-sm" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <!-- Sign Agreement (NEW) -->
                                    @if($agreement->status == 'pending' && !$developerSigned)
                                    <a href="{{ route('developer.billing.view-agreement-for-signing', $agreement->id) }}" 
                                       class="btn btn-success btn-sm" title="Sign Agreement">
                                        <i class="fas fa-signature"></i>
                                    </a>
                                    @endif
                                    
                                    <!-- Download Signed Agreement (NEW) -->
                                    @if($agreement->signed_agreement_pdf_path)
                                    <a href="{{ route('developer.billing.download-signed-agreement', $agreement->id) }}" 
                                       class="btn btn-primary btn-sm" title="Download Signed Agreement">
                                        <i class="fas fa-file-signature"></i>
                                    </a>
                                    @endif
                                    
                                    <!-- Signature Audit (NEW) -->
                                    @if($agreement->signatures->count() > 0)
                                    <a href="{{ route('developer.billing.signature-audit', $agreement->id) }}" 
                                       class="btn btn-secondary btn-sm" title="Signature Audit">
                                        <i class="fas fa-history"></i>
                                    </a>
                                    @endif
                                    
                                    <!-- Record Payment -->
                                    @if($agreement->status == 'active' && $agreement->amount_received < $agreement->amount)
                                    <button onclick="recordPayment({{ $agreement->id }})"
                                            class="btn btn-warning btn-sm" title="Record Payment">
                                        <i class="fas fa-money-check"></i>
                                    </button>
                                    @endif
                                    
                                    <!-- Download Agreement PDF -->
                                    <button onclick="downloadAgreement({{ $agreement->id }})"
                                            class="btn btn-secondary btn-sm" title="Download PDF">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    
                                    <!-- Generate PDF (NEW) -->
                                    @if(!$agreement->agreement_pdf_path)
                                    <button onclick="generateAgreementPdf({{ $agreement->id }})"
                                            class="btn btn-info btn-sm" title="Generate PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </button>
                                    @endif
                                    
                                    <!-- Revoke Signature (NEW) -->
                                    @if($agreement->status == 'active' && $developerSigned && !$superAdminSigned)
                                    <button onclick="showRevokeSignatureModal({{ $agreement->id }})"
                                            class="btn btn-danger btn-sm" title="Revoke Signature">
                                        <i class="fas fa-undo-alt"></i>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="table-cell text-center py-8">
                                <i class="fas fa-inbox text-3xl text-secondary mb-2"></i>
                                <p class="text-secondary">No agreements found</p>
                                @if(request()->hasAny(['status', 'payment_status', 'frequency', 'search', 'date_range']))
                                <p class="text-sm text-secondary mt-1">Try adjusting your filters</p>
                                <a href="{{ route('developer.billing.agreements-list') }}" class="btn btn-secondary btn-sm mt-2">
                                    <i class="fas fa-redo mr-1"></i> Clear Filters
                                </a>
                                @else
                                <p class="text-sm text-secondary mt-1">You don't have any billing agreements yet</p>
                                <a href="{{ route('developer.billing.dashboard') }}" class="btn btn-primary btn-sm mt-2">
                                    <i class="fas fa-plus mr-1"></i> Create Agreement
                                </a>
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            @if($agreements->hasPages())
            <div class="mt-6 pt-6 border-t section-divider">
                <div class="flex flex-col md:flex-row items-center justify-between">
                    <div class="text-sm text-secondary mb-4 md:mb-0">
                        Showing {{ $agreements->firstItem() }} - {{ $agreements->lastItem() }} of {{ $agreements->total() }}
                    </div>
                    <div class="flex items-center space-x-2">
                        @if($agreements->onFirstPage())
                        <span class="btn btn-secondary btn-sm opacity-50 cursor-not-allowed">
                            <i class="fas fa-chevron-left mr-1"></i> Previous
                        </span>
                        @else
                        <a href="{{ $agreements->previousPageUrl() }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-chevron-left mr-1"></i> Previous
                        </a>
                        @endif
                        
                        <div class="hidden md:flex items-center space-x-1">
                            @foreach($agreements->getUrlRange(1, $agreements->lastPage()) as $page => $url)
                                @if($page == $agreements->currentPage())
                                <span class="w-8 h-8 flex items-center justify-center bg-primary text-white rounded-full text-sm font-medium">
                                    {{ $page }}
                                </span>
                                @else
                                <a href="{{ $url }}" class="w-8 h-8 flex items-center justify-center border border-secondary rounded-full text-sm hover:bg-secondary/10">
                                    {{ $page }}
                                </a>
                                @endif
                            @endforeach
                        </div>
                        
                        <div class="md:hidden text-sm font-medium">
                            Page {{ $agreements->currentPage() }} of {{ $agreements->lastPage() }}
                        </div>
                        
                        @if($agreements->hasMorePages())
                        <a href="{{ $agreements->nextPageUrl() }}" class="btn btn-secondary btn-sm">
                            Next <i class="fas fa-chevron-right ml-1"></i>
                        </a>
                        @else
                        <span class="btn btn-secondary btn-sm opacity-50 cursor-not-allowed">
                            Next <i class="fas fa-chevron-right ml-1"></i>
                        </span>
                        @endif
                        
                        <select onchange="updatePerPage(this.value)" class="form-select form-select-sm w-auto">
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
</div>

<!-- Record Payment Modal -->
<div id="recordPaymentModal" class="modal hidden">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-money-check mr-2"></i> Record Payment
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('recordPaymentModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="recordPaymentForm" method="POST" action="">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="form-label">Amount *</label>
                        <input type="number" name="amount_paid" id="paymentAmountInput" class="form-input w-full" min="0" step="0.01" required>
                        <p class="form-help">Outstanding: <span id="outstandingAmount">GH₵0.00</span></p>
                    </div>
                    <div>
                        <label class="form-label">Payment Date *</label>
                        <input type="date" name="payment_date" class="form-input w-full" max="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div>
                        <label class="form-label">Payment Method *</label>
                        <select name="payment_method" class="form-select w-full" required>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="cash">Cash</option>
                            <option value="paystack">Paystack</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Transaction Reference</label>
                        <input type="text" name="transaction_reference" class="form-input w-full" placeholder="Bank reference or transaction ID">
                    </div>
                    <div>
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-textarea w-full" rows="2" placeholder="Any additional notes about this payment"></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('recordPaymentModal')">Cancel</button>
            <button type="submit" form="recordPaymentForm" class="btn btn-success">
                <i class="fas fa-check mr-2"></i> Record Payment
            </button>
        </div>
    </div>
</div>

<!-- NEW: Revoke Signature Modal -->
<div id="revokeSignatureModal" class="modal hidden">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="fas fa-undo-alt mr-2"></i> Revoke Signature
            </h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('revokeSignatureModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="revokeSignatureForm" method="POST" action="">
                @csrf
                <div class="space-y-4">
                    <div class="alert alert-warning">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2 text-warning"></i>
                            <div>
                                <p class="font-bold">Warning!</p>
                                <p class="text-sm">Revoking your signature will put the agreement back into pending status. The super admin will need to sign again.</p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Reason for Revoking (Optional)</label>
                        <textarea name="revoke_reason" class="form-textarea w-full" rows="3" placeholder="Please explain why you want to revoke your signature..."></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('revokeSignatureModal')">Cancel</button>
            <button type="submit" form="revokeSignatureForm" class="btn btn-danger">
                <i class="fas fa-undo-alt mr-2"></i> Revoke Signature
            </button>
        </div>
    </div>
</div>

<!-- NEW: Generate PDF Loading Modal -->
<div id="generatePdfModal" class="modal hidden">
    <div class="modal-container" style="max-width: 400px;">
        <div class="modal-body text-center py-8">
            <i class="fas fa-spinner fa-spin text-3xl text-primary mb-3"></i>
            <p>Generating PDF document...</p>
            <p class="text-sm text-secondary mt-2">Please wait</p>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

<script>
let currentAgreementId = null;

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

function toggleCustomDates(value) {
    const customDateRange = document.getElementById('customDateRange');
    if (customDateRange) {
        customDateRange.classList.toggle('hidden', value !== 'custom');
    }
}

function recordPayment(agreementId) {
    const modal = document.getElementById('recordPaymentModal');
    if (modal) {
        const form = modal.querySelector('#recordPaymentForm');
        form.action = `/developer/billing/confirm-payment`;
        
        // Add agreement_id hidden input
        let agreementInput = form.querySelector('input[name="agreement_id"]');
        if (!agreementInput) {
            agreementInput = document.createElement('input');
            agreementInput.type = 'hidden';
            agreementInput.name = 'agreement_id';
            form.appendChild(agreementInput);
        }
        agreementInput.value = agreementId;
        
        // Set default date to today
        const dateInput = modal.querySelector('input[name="payment_date"]');
        dateInput.value = new Date().toISOString().split('T')[0];
        
        // Fetch outstanding amount
        fetch(`/developer/billing/agreements/${agreementId}/details`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const outstanding = data.agreement.amount - data.agreement.amount_received;
                    document.getElementById('outstandingAmount').textContent = 
                        formatCurrencyDisplay(outstanding, data.agreement.currency);
                    
                    const amountInput = modal.querySelector('#paymentAmountInput');
                    amountInput.max = outstanding;
                    amountInput.value = outstanding > 0 ? outstanding : 0;
                    amountInput.step = '0.01';
                }
            })
            .catch(error => {
                showToast('Error fetching agreement details', 'error');
            });
        
        openModal('recordPaymentModal');
    }
}

function downloadAgreement(agreementId) {
    window.location.href = `/developer/billing/agreement/${agreementId}/generate-pdf`;
}

function generateAgreementPdf(agreementId) {
    openModal('generatePdfModal');
    
    fetch(`/developer/billing/agreement/${agreementId}/generate-pdf`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        closeModal('generatePdfModal');
        if (data.success) {
            showToast('PDF generated successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Failed to generate PDF: ' + data.message, 'error');
        }
    })
    .catch(error => {
        closeModal('generatePdfModal');
        showToast('Error generating PDF', 'error');
    });
}

function showRevokeSignatureModal(agreementId) {
    const modal = document.getElementById('revokeSignatureModal');
    if (modal) {
        const form = modal.querySelector('#revokeSignatureForm');
        form.action = `/developer/billing/agreement/${agreementId}/revoke-signature`;
        openModal('revokeSignatureModal');
    }
}

function exportAgreements() {
    const params = new URLSearchParams(window.location.search);
    params.set('export_type', 'agreements');
    showToast('Preparing export...', 'info');
    
    const overlay = document.createElement('div');
    overlay.id = 'exportOverlay';
    overlay.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50';
    overlay.innerHTML = '<div class="bg-white dark:bg-gray-800 p-6 rounded-lg"><i class="fas fa-spinner fa-spin mr-2"></i> Preparing export...</div>';
    document.body.appendChild(overlay);
    
    setTimeout(() => {
        window.location.href = '/developer/billing/export?' + params.toString();
        document.getElementById('exportOverlay')?.remove();
    }, 1000);
}

function updatePerPage(perPage) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', perPage);
    window.location.href = url.toString();
}

function formatCurrencyDisplay(amount, currency = 'GHS') {
    if (!amount) return 'GH₵0.00';
    if (currency === 'GHS') {
        return 'GH₵' + amount.toFixed(2);
    }
    return currency + ' ' + amount.toFixed(2);
}

function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    const colors = {
        success: 'bg-green-500', error: 'bg-red-500', warning: 'bg-yellow-500', info: 'bg-blue-500'
    };
    toast.className = `${colors[type]} text-white px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 transform transition-all duration-300 translate-x-full`;
    toast.innerHTML = `<span>${message}</span><button onclick="this.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">&times;</button>`;
    container.appendChild(toast);
    
    setTimeout(() => toast.classList.remove('translate-x-full'), 10);
    setTimeout(() => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Close modals when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        });
    });
    
    // Initialize custom date range visibility
    const dateRangeSelect = document.querySelector('select[name="date_range"]');
    if (dateRangeSelect) {
        toggleCustomDates(dateRangeSelect.value);
    }
    
    // Close modals with Escape key
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
});
</script>

<style scoped>
/* Add component styles */
.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
}

.badge-success { background-color: rgba(var(--success-rgb), 0.1); color: var(--success); }
.badge-warning { background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning); }
.badge-danger { background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger); }
.badge-info { background-color: rgba(var(--info-rgb), 0.1); color: var(--info); }
.badge-primary { background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); }
.badge-secondary { background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary); }

.btn-primary-small { background-color: var(--primary); color: white; padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.875rem; transition: all 0.2s; display: inline-flex; align-items: center; }
.btn-primary-small:hover { transform: translateY(-1px); opacity: 0.9; }
.btn-success-small { background-color: var(--success); color: white; padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.875rem; transition: all 0.2s; display: inline-flex; align-items: center; }
.btn-success-small:hover { transform: translateY(-1px); opacity: 0.9; }

.table-header { padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color); }
.table-cell { padding: 1rem; border-bottom: 1px solid var(--border-color); }
.table-cell-primary { font-weight: 500; color: var(--text-primary); }
.table-cell-secondary { color: var(--text-secondary); }

.modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(5px); }
.modal.hidden { display: none; }
.modal-container { background-color: var(--card-bg); border-radius: 16px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2); border: 1px solid var(--border-color); }
.modal-header { padding: 1.5rem 1.5rem 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
.modal-title { font-size: 1.25rem; font-weight: 600; color: var(--text-primary); margin: 0; }
.modal-close-btn { background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.25rem; padding: 0.25rem; border-radius: 6px; }
.modal-close-btn:hover { background-color: rgba(var(--primary-rgb), 0.1); }
.modal-body { padding: 1.5rem; }
.modal-footer { padding: 1rem 1.5rem 1.5rem; border-top: 1px solid var(--border-color); display: flex; gap: 1rem; justify-content: flex-end; }

.alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
.alert-info { background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.2); color: var(--info); }
.alert-warning { background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2); color: var(--warning); }

.form-label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: var(--text-primary); }
.form-input, .form-select, .form-textarea { background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.5rem 0.75rem; width: 100%; transition: all 0.2s; }
.form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1); }
.form-help { font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem; }

@media (max-width: 768px) {
    .modal-footer { flex-direction: column; }
    .modal-footer button { width: 100%; margin-bottom: 0.5rem; }
}
</style>
@endsection