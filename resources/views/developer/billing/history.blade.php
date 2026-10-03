{{-- resources/views/developer/billing/history.blade.php --}}
@extends('layouts.dev')

@php
    $pageTitle = 'Billing History';
    $user = auth()->user();
    
    function formatCurrency($amount, $currency = 'GHS') {
        if (empty($amount)) return 'GH₵0.00';
        if ($currency === 'GHS') {
            return 'GH₵' . number_format($amount, 2);
        }
        return $currency . ' ' . number_format($amount, 2);
    }
    
    // Status colors
    function getInvoiceStatusColor($status) {
        $colors = [
            'paid' => 'success',
            'pending' => 'warning',
            'overdue' => 'danger',
            'cancelled' => 'secondary',
            'refunded' => 'info'
        ];
        return $colors[$status] ?? 'secondary';
    }
    
    // Invoice type icons
    function getInvoiceTypeIcon($type) {
        $icons = [
            'recurring' => 'fas fa-redo',
            'one_time' => 'fas fa-file-invoice',
            'additional' => 'fas fa-plus-circle',
            'penalty' => 'fas fa-exclamation-triangle',
            'adjustment' => 'fas fa-adjust'
        ];
        return $icons[$type] ?? 'fas fa-file-invoice';
    }
@endphp

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-history mr-2"></i> 
                    Billing History & Invoices
                </h2>
                <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-2"></i>
                    <span>View all invoices and payment history</span>
                    <span class="mx-2">•</span>
                    <span class="font-medium">{{ $invoices->total() }} total invoices</span>
                </div>
            </div>
            <div class="flex items-center space-x-3" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-2"></i> {{ now()->format('F j, Y') }}
                <a href="{{ route('developer.billing.dashboard') }}" class="btn-secondary flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Dashboard
                </a>
                <button onclick="showCustomInvoiceModal()" class="btn-secondary flex items-center">
                    <i class="fas fa-plus mr-2"></i> New Invoice
                </button>
                <!-- NEW: Recurring Billing Button -->
                <button onclick="processRecurringBilling()" class="btn-primary flex items-center">
                    <i class="fas fa-sync-alt mr-2"></i> Process Recurring
                </button>
            </div>
        </div>
        
        <!-- NEW: Quick Navigation Tabs -->
        <div class="border-t px-6" style="border-color: var(--border-color);">
            <div class="flex space-x-1">
                <a href="{{ route('developer.billing.history') }}" 
                   class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ !request()->has('tab') ? 'border-primary text-primary' : 'border-transparent text-secondary hover:text-primary' }}">
                    Invoices
                </a>
                <a href="{{ route('developer.billing.history', ['tab' => 'payments']) }}" 
                   class="px-4 py-3 text-sm font-medium border-b-2 transition-colors {{ request('tab') == 'payments' ? 'border-primary text-primary' : 'border-transparent text-secondary hover:text-primary' }}">
                    Payments
                </a>
                <a href="{{ route('developer.billing.super-admin-payments') }}" 
                   class="px-4 py-3 text-sm font-medium border-b-2 border-transparent text-secondary hover:text-primary transition-colors">
                    SA Requests
                </a>
                <a href="{{ route('developer.billing.statistics') }}" 
                   class="px-4 py-3 text-sm font-medium border-b-2 border-transparent text-secondary hover:text-primary transition-colors">
                    Statistics
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="card p-5">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Total Invoices</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ $stats['total_invoices'] ?? 0 }}</h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-file-invoice text-xl" style="color: var(--info);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-5">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Paid Invoices</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ $stats['paid_invoices'] ?? 0 }}</h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-check-circle text-xl" style="color: var(--success);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-5">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Pending Invoices</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ $stats['pending_invoices'] ?? 0 }}</h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-clock text-xl" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-5">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Overdue Invoices</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ $stats['overdue_invoices'] ?? 0 }}</h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--danger-rgb), 0.1);">
                    <i class="fas fa-exclamation-circle text-xl" style="color: var(--danger);"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Card -->
    <div class="card">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-bar mr-2"></i> Financial Summary
                </h3>
                <button onclick="refreshStats()" class="text-sm hover:text-primary transition-colors" style="color: var(--text-secondary);">
                    <i class="fas fa-sync-alt mr-1"></i> Refresh
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="text-center p-4 border rounded-lg" style="border-color: var(--border-color);">
                    <p class="text-sm" style="color: var(--text-secondary);">Total Billing Amount</p>
                    <h4 class="text-2xl font-bold mt-2" style="color: var(--text-primary);">
                        {{ formatCurrency($stats['total_amount'] ?? 0) }}
                    </h4>
                </div>
                
                <div class="text-center p-4 border rounded-lg" style="border-color: var(--border-color);">
                    <p class="text-sm" style="color: var(--text-secondary);">Total Received</p>
                    <h4 class="text-2xl font-bold mt-2" style="color: var(--success);">
                        {{ formatCurrency($stats['total_received'] ?? 0) }}
                    </h4>
                    <p class="text-xs mt-1" style="color: var(--success);">
                        @if(($stats['total_amount'] ?? 0) > 0)
                            {{ round((($stats['total_received'] ?? 0) / ($stats['total_amount'] ?? 1)) * 100, 1) }}% collected
                        @else
                            0% collected
                        @endif
                    </p>
                </div>
                
                <div class="text-center p-4 border rounded-lg" style="border-color: var(--border-color);">
                    <p class="text-sm" style="color: var(--text-secondary);">Collection Rate</p>
                    <h4 class="text-2xl font-bold mt-2" style="color: var(--primary);">
                        @if(($stats['total_invoices'] ?? 0) > 0)
                            {{ round((($stats['paid_invoices'] ?? 0) / ($stats['total_invoices'] ?? 1)) * 100, 1) }}%
                        @else
                            0%
                        @endif
                    </h4>
                </div>
                
                <!-- NEW: Recurring Status -->
                <div class="text-center p-4 border rounded-lg" style="border-color: var(--border-color);">
                    <p class="text-sm" style="color: var(--text-secondary);">Recurring Billing</p>
                    <h4 class="text-2xl font-bold mt-2" style="color: var(--info);">
                        {{ $stats['recurring_invoices'] ?? 0 }}
                    </h4>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">Active recurring invoices</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-filter mr-2"></i> Filter Invoices
                </h3>
            </div>
            
            <form method="GET" action="{{ route('developer.billing.history') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Invoice Type</label>
                        <select name="type" class="w-full p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">All Types</option>
                            <option value="recurring" {{ request('type') == 'recurring' ? 'selected' : '' }}>Recurring</option>
                            <option value="custom" {{ request('type') == 'custom' ? 'selected' : '' }}>Custom</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Status</label>
                        <select name="status" class="w-full p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">All Statuses</option>
                            <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Date Range</label>
                        <select name="date_range" class="w-full p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);" onchange="toggleCustomDates(this.value)">
                            <option value="">All Time</option>
                            <option value="this_month" {{ request('date_range') == 'this_month' ? 'selected' : '' }}>This Month</option>
                            <option value="last_month" {{ request('date_range') == 'last_month' ? 'selected' : '' }}>Last Month</option>
                            <option value="this_quarter" {{ request('date_range') == 'this_quarter' ? 'selected' : '' }}>This Quarter</option>
                            <option value="this_year" {{ request('date_range') == 'this_year' ? 'selected' : '' }}>This Year</option>
                            <option value="custom" {{ request('date_range') == 'custom' ? 'selected' : '' }}>Custom Range</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Sort By</label>
                        <select name="sort_by" class="w-full p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="created_at_desc" {{ request('sort_by', 'created_at_desc') == 'created_at_desc' ? 'selected' : '' }}>Newest First</option>
                            <option value="created_at_asc" {{ request('sort_by') == 'created_at_asc' ? 'selected' : '' }}>Oldest First</option>
                            <option value="amount_desc" {{ request('sort_by') == 'amount_desc' ? 'selected' : '' }}>Highest Amount</option>
                            <option value="amount_asc" {{ request('sort_by') == 'amount_asc' ? 'selected' : '' }}>Lowest Amount</option>
                            <option value="due_date_asc" {{ request('sort_by') == 'due_date_asc' ? 'selected' : '' }}>Due Date (Soonest)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Show Recurring</label>
                        <select name="show_recurring" class="w-full p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <option value="">All</option>
                            <option value="1" {{ request('show_recurring') == '1' ? 'selected' : '' }}>Show Recurring Only</option>
                            <option value="0" {{ request('show_recurring') == '0' ? 'selected' : '' }}>Hide Recurring</option>
                        </select>
                    </div>
                </div>
                
                <div id="customDateRange" class="grid grid-cols-1 md:grid-cols-2 gap-4 {{ request('date_range') != 'custom' ? 'hidden' : '' }}">
                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">Start Date</label>
                        <input type="date" name="start_date" value="{{ request('start_date') }}" class="w-full p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    </div>
                    <div>
                        <label class="block mb-2 text-sm" style="color: var(--text-secondary);">End Date</label>
                        <input type="date" name="end_date" value="{{ request('end_date') }}" class="w-full p-3 border rounded-lg" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                    </div>
                </div>
                
                <div class="pt-4 border-t" style="border-color: var(--border-color);">
                    <div class="flex justify-between items-center">
                        <a href="{{ route('developer.billing.history') }}" class="px-4 py-2 rounded-lg border" style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                            <i class="fas fa-redo mr-2"></i> Reset Filters
                        </a>
                        <div class="flex space-x-2">
                            <button type="button" onclick="exportInvoices()" class="px-4 py-2 rounded-lg" style="background-color: var(--success); color: white;">
                                <i class="fas fa-download mr-2"></i> Export CSV
                            </button>
                            <button type="submit" class="px-4 py-2 rounded-lg" style="background-color: var(--primary); color: white;">
                                <i class="fas fa-filter mr-2"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Invoices Table Card -->
    <div class="card">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-file-invoice mr-2"></i> Invoice List
                </h3>
                <div class="text-sm" style="color: var(--text-secondary);">
                    Showing {{ $invoices->firstItem() }} - {{ $invoices->lastItem() }} of {{ $invoices->total() }}
                </div>
            </div>
            
            @if($invoices->isEmpty())
            <div class="text-center py-8">
                <i class="fas fa-inbox text-3xl mb-4" style="color: var(--text-secondary);"></i>
                <p style="color: var(--text-secondary);">No invoices found</p>
            </div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b" style="border-color: var(--border-color); background-color: var(--bg-secondary);">
                            <th class="text-left p-4">Invoice</th>
                            <th class="text-left p-4">Type</th>
                            <th class="text-left p-4">Amount</th>
                            <th class="text-left p-4">Issue Date</th>
                            <th class="text-left p-4">Due Date</th>
                            <th class="text-left p-4">Status</th>
                            <th class="text-left p-4">Description</th>
                            <th class="text-left p-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $invoice)
                        <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors" style="border-color: var(--border-color);">
                            <td class="p-4">
                                <div class="flex items-center">
                                    <div class="mr-3">
                                        <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                            <i class="{{ getInvoiceTypeIcon($invoice->invoice_type) }}"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <div style="color: var(--text-primary); font-weight: 500;">{{ $invoice->invoice_number }}</div>
                                        <div class="text-sm mt-1" style="color: var(--text-secondary);">
                                            {{ $invoice->is_recurring ? 'Recurring' : ($invoice->is_custom ? 'Custom' : 'Standard') }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                    {{ ucfirst(str_replace('_', ' ', $invoice->invoice_type)) }}
                                </span>
                            </td>
                            <td class="p-4">
                                <div style="color: var(--text-primary); font-weight: 500;">
                                    {{ formatCurrency($invoice->amount, $invoice->currency) }}
                                </div>
                            </td>
                            <td class="p-4" style="color: var(--text-secondary);">
                                {{ $invoice->issue_date->format('M d, Y') }}
                            </td>
                            <td class="p-4">
                                <div style="color: {{ $invoice->due_date->isPast() && $invoice->status == 'pending' ? 'var(--danger)' : 'var(--text-primary)' }};">
                                    {{ $invoice->due_date->format('M d, Y') }}
                                </div>
                            </td>
                            <td class="p-4">
                                @php $statusColor = getInvoiceStatusColor($invoice->status); @endphp
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm" style="background-color: rgba(var(--{{ $statusColor }}-rgb), 0.1); color: var(--{{ $statusColor }});">
                                    {{ ucfirst($invoice->status) }}
                                </span>
                            </td>
                            <td class="p-4" style="color: var(--text-secondary);">
                                <div class="truncate max-w-xs" title="{{ $invoice->description }}">
                                    {{ Str::limit($invoice->description, 40) }}
                                </div>
                            </td>
                            <td class="p-4">
                                <div class="flex items-center space-x-2">
                                    <button onclick="viewInvoice({{ $invoice->id }})" class="action-btn" title="View Details" style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick="downloadInvoice({{ $invoice->id }})" class="action-btn" title="Download PDF" style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-secondary);">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    @if($invoice->status == 'pending')
                                    <button onclick="markAsPaid({{ $invoice->id }})" class="action-btn" title="Mark as Paid" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    @endif
                                    @if(in_array($invoice->status, ['pending', 'overdue']))
                                    <button onclick="sendInvoiceReminder({{ $invoice->id }})" class="action-btn" title="Send Reminder" style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                                        <i class="fas fa-bell"></i>
                                    </button>
                                    @endif
                                    <!-- NEW: View Payment Details Button -->
                                    @if($invoice->status == 'paid')
                                    <button onclick="viewPaymentDetails({{ $invoice->id }})" class="action-btn" title="Payment Details" style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                                        <i class="fas fa-money-check-alt"></i>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            @if($invoices->hasPages())
            <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                {{ $invoices->links() }}
            </div>
            @endif
            @endif
        </div>
    </div>
</div>

<!-- Custom Invoice Modal -->
<div id="customInvoiceModal" class="modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-file-invoice-dollar mr-2"></i> Create Custom Invoice</h3>
            <button type="button" class="modal-close" onclick="closeModal('customInvoiceModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="customInvoiceForm" method="POST" action="{{ route('developer.billing.generate-custom-invoice') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block mb-2 text-sm">Amount *</label>
                        <input type="number" name="amount" class="w-full p-3 border rounded-lg" min="1" max="100000" step="0.01" required>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm">Description *</label>
                        <textarea name="description" rows="3" class="w-full p-3 border rounded-lg" placeholder="Describe the service or charge" required></textarea>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm">Invoice Type *</label>
                        <select name="invoice_type" class="w-full p-3 border rounded-lg" required>
                            <option value="one_time">One Time Charge</option>
                            <option value="additional">Additional Service</option>
                            <option value="penalty">Penalty/Fine</option>
                            <option value="adjustment">Adjustment</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm">Due Date *</label>
                        <input type="date" name="due_date" class="w-full p-3 border rounded-lg" min="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm">Currency</label>
                        <select name="currency" class="w-full p-3 border rounded-lg">
                            <option value="GHS">GHS - Ghanaian Cedi</option>
                            <option value="USD">USD - US Dollar</option>
                            <option value="EUR">EUR - Euro</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="flex-1 py-3 rounded-lg border" onclick="closeModal('customInvoiceModal')">Cancel</button>
            <button type="submit" form="customInvoiceForm" class="flex-1 py-3 rounded-lg" style="background-color: var(--primary); color: white;">Create Invoice</button>
        </div>
    </div>
</div>

<!-- View Invoice Modal -->
<div id="viewInvoiceModal" class="modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-container" style="max-width: 800px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-file-invoice mr-2"></i> Invoice Details</h3>
            <button type="button" class="modal-close" onclick="closeModal('viewInvoiceModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="invoiceDetails">Loading...</div>
        </div>
        <div class="modal-footer">
            <button type="button" class="flex-1 py-3 rounded-lg border" onclick="closeModal('viewInvoiceModal')">Close</button>
            <button onclick="downloadCurrentInvoice()" class="flex-1 py-3 rounded-lg" style="background-color: var(--primary); color: white;">Download PDF</button>
        </div>
    </div>
</div>

<!-- Mark as Paid Modal -->
<div id="markAsPaidModal" class="modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-check-circle mr-2"></i> Mark Invoice as Paid</h3>
            <button type="button" class="modal-close" onclick="closeModal('markAsPaidModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="markAsPaidForm" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="space-y-4">
                    <div>
                        <label class="block mb-2 text-sm">Payment Amount *</label>
                        <input type="number" name="amount_paid" id="paymentAmountInput" class="w-full p-3 border rounded-lg" min="0" step="0.01" required>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm">Payment Date *</label>
                        <input type="date" name="payment_date" class="w-full p-3 border rounded-lg" max="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm">Payment Method *</label>
                        <select name="payment_method" class="w-full p-3 border rounded-lg" required>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="cash">Cash</option>
                            <option value="paystack">Paystack</option>
                        </select>
                    </div>
                    <div>
                        <label class="block mb-2 text-sm">Transaction Reference</label>
                        <input type="text" name="transaction_reference" class="w-full p-3 border rounded-lg" placeholder="Bank reference or transaction ID">
                    </div>
                    <div>
                        <label class="block mb-2 text-sm">Notes</label>
                        <textarea name="notes" rows="2" class="w-full p-3 border rounded-lg" placeholder="Any additional notes"></textarea>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="flex-1 py-3 rounded-lg border" onclick="closeModal('markAsPaidModal')">Cancel</button>
            <button type="submit" form="markAsPaidForm" class="flex-1 py-3 rounded-lg" style="background-color: var(--success); color: white;">Mark as Paid</button>
        </div>
    </div>
</div>

<!-- NEW: Payment Details Modal -->
<div id="paymentDetailsModal" class="modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-container" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-money-check-alt mr-2"></i> Payment Details</h3>
            <button type="button" class="modal-close" onclick="closeModal('paymentDetailsModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="paymentDetailsContent">Loading...</div>
        </div>
        <div class="modal-footer">
            <button type="button" class="flex-1 py-3 rounded-lg border" onclick="closeModal('paymentDetailsModal')">Close</button>
        </div>
    </div>
</div>

<!-- NEW: Recurring Billing Confirmation Modal -->
<div id="recurringModal" class="modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-container" style="max-width: 450px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-sync-alt mr-2"></i> Process Recurring Billing</h3>
            <button type="button" class="modal-close" onclick="closeModal('recurringModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <p class="mb-4">This will process all recurring billing for the current period. New invoices will be generated for all active agreements.</p>
            <div class="p-3 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.1); border: 1px solid rgba(var(--warning-rgb), 0.2);">
                <p class="text-sm" style="color: var(--warning);">
                    <i class="fas fa-info-circle mr-2"></i>
                    This action will generate invoices for all active agreements based on their billing frequency.
                </p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="flex-1 py-3 rounded-lg border" onclick="closeModal('recurringModal')">Cancel</button>
            <button type="button" onclick="confirmRecurringBilling()" class="flex-1 py-3 rounded-lg" style="background-color: var(--primary); color: white;">
                <i class="fas fa-play mr-2"></i> Process Now
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@push('scripts')
<script>
let currentInvoiceId = null;
let currentPaymentId = null;

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
}

function toggleCustomDates(value) {
    const customDateRange = document.getElementById('customDateRange');
    if (customDateRange) {
        customDateRange.classList.toggle('hidden', value !== 'custom');
    }
}

function showCustomInvoiceModal() {
    const modal = document.getElementById('customInvoiceModal');
    if (modal) {
        const dueDateInput = modal.querySelector('input[name="due_date"]');
        const defaultDate = new Date();
        defaultDate.setDate(defaultDate.getDate() + 30);
        dueDateInput.value = defaultDate.toISOString().split('T')[0];
        modal.style.display = 'block';
    }
}

function viewInvoice(invoiceId) {
    currentInvoiceId = invoiceId;
    const modal = document.getElementById('viewInvoiceModal');
    if (modal) {
        document.getElementById('invoiceDetails').innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl"></i><p class="mt-2">Loading invoice details...</p></div>';
        modal.style.display = 'block';
        
        fetch(`/developer/billing/invoices/${invoiceId}/details`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('invoiceDetails').innerHTML = generateInvoiceHtml(data.invoice);
                } else {
                    document.getElementById('invoiceDetails').innerHTML = `<div class="p-4 rounded-lg bg-red-100 text-red-800">Error: ${data.message}</div>`;
                }
            })
            .catch(error => {
                document.getElementById('invoiceDetails').innerHTML = '<div class="p-4 rounded-lg bg-red-100 text-red-800">Error loading invoice details</div>';
            });
    }
}

function generateInvoiceHtml(invoice) {
    return `
        <div class="space-y-4">
            <div class="flex justify-between items-start pb-4 border-b">
                <div>
                    <h4 class="font-bold text-lg">${invoice.invoice_number}</h4>
                    <p class="text-sm text-gray-500">Issued: ${new Date(invoice.issue_date).toLocaleDateString()}</p>
                </div>
                <div class="text-right">
                    <span class="px-3 py-1 rounded-full text-sm ${invoice.status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
                        ${invoice.status.toUpperCase()}
                    </span>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><strong>Amount:</strong> ${invoice.currency} ${Number(invoice.amount).toFixed(2)}</div>
                <div><strong>Due Date:</strong> ${new Date(invoice.due_date).toLocaleDateString()}</div>
                <div><strong>Type:</strong> ${invoice.invoice_type}</div>
                <div><strong>Recurring:</strong> ${invoice.is_recurring ? 'Yes' : 'No'}</div>
            </div>
            <div>
                <strong>Description:</strong>
                <p class="mt-1">${invoice.description}</p>
            </div>
            ${invoice.status === 'paid' && invoice.payment ? `
            <div class="mt-4 p-4 rounded-lg bg-green-50 border border-green-200">
                <strong class="text-green-800">Payment Information</strong>
                <div class="grid grid-cols-2 gap-2 mt-2 text-sm">
                    <div>Amount Paid: ${invoice.currency} ${Number(invoice.payment.amount_paid).toFixed(2)}</div>
                    <div>Payment Date: ${new Date(invoice.payment.payment_date).toLocaleDateString()}</div>
                    <div>Method: ${invoice.payment.payment_method}</div>
                    <div>Reference: ${invoice.payment.transaction_reference || 'N/A'}</div>
                </div>
            </div>
            ` : ''}
        </div>
    `;
}

function downloadInvoice(invoiceId) {
    window.location.href = `/developer/billing/invoices/${invoiceId}/download`;
}

function downloadCurrentInvoice() {
    if (currentInvoiceId) downloadInvoice(currentInvoiceId);
}

function markAsPaid(invoiceId) {
    const modal = document.getElementById('markAsPaidModal');
    if (modal) {
        const form = modal.querySelector('#markAsPaidForm');
        form.action = `/developer/billing/invoices/${invoiceId}/mark-paid`;
        
        const dateInput = modal.querySelector('input[name="payment_date"]');
        dateInput.value = new Date().toISOString().split('T')[0];
        
        fetch(`/developer/billing/invoices/${invoiceId}/amount`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('paymentAmountInput').value = data.amount;
                }
            });
        
        modal.style.display = 'block';
    }
}

function sendInvoiceReminder(invoiceId) {
    if (confirm('Send payment reminder for this invoice?')) {
        showToast('Sending reminder...', 'info');
        
        fetch(`/developer/billing/invoices/${invoiceId}/send-reminder`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Reminder sent successfully!', 'success');
            } else {
                showToast('Failed: ' + data.message, 'error');
            }
        })
        .catch(error => showToast('Error sending reminder', 'error'));
    }
}

// NEW: View Payment Details
function viewPaymentDetails(invoiceId) {
    const modal = document.getElementById('paymentDetailsModal');
    if (modal) {
        document.getElementById('paymentDetailsContent').innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl"></i><p class="mt-2">Loading payment details...</p></div>';
        modal.style.display = 'block';
        
        fetch(`/developer/billing/payments/${invoiceId}/details`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('paymentDetailsContent').innerHTML = generatePaymentDetailsHtml(data.payment);
                } else {
                    document.getElementById('paymentDetailsContent').innerHTML = `<div class="p-4 rounded-lg bg-red-100 text-red-800">${data.message}</div>`;
                }
            })
            .catch(error => {
                document.getElementById('paymentDetailsContent').innerHTML = '<div class="p-4 rounded-lg bg-red-100 text-red-800">Error loading payment details</div>';
            });
    }
}

function generatePaymentDetailsHtml(payment) {
    return `
        <div class="space-y-4">
            <div class="flex justify-between items-start pb-4 border-b">
                <div>
                    <h4 class="font-bold text-lg">Payment #${payment.id}</h4>
                    <p class="text-sm text-gray-500">Status: ${payment.status}</p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><strong>Amount Paid:</strong> ${payment.currency || 'GHS'} ${Number(payment.amount_paid).toFixed(2)}</div>
                <div><strong>Payment Date:</strong> ${new Date(payment.payment_date).toLocaleDateString()}</div>
                <div><strong>Method:</strong> ${payment.payment_method}</div>
                <div><strong>Reference:</strong> ${payment.transaction_reference || 'N/A'}</div>
                <div><strong>Confirmed By:</strong> ${payment.confirmed_by_name || 'System'}</div>
                <div><strong>Confirmed At:</strong> ${payment.confirmed_at ? new Date(payment.confirmed_at).toLocaleString() : 'Pending'}</div>
            </div>
            ${payment.notes ? `<div><strong>Notes:</strong><p class="mt-1">${payment.notes}</p></div>` : ''}
        </div>
    `;
}

// NEW: Process Recurring Billing
function processRecurringBilling() {
    const modal = document.getElementById('recurringModal');
    if (modal) modal.style.display = 'block';
}

function confirmRecurringBilling() {
    closeModal('recurringModal');
    showToast('Processing recurring billing...', 'info');
    
    fetch('{{ route("developer.billing.process-recurring") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Recurring billing processed successfully!', 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            showToast('Failed: ' + data.message, 'error');
        }
    })
    .catch(error => showToast('Error processing recurring billing', 'error'));
}

function refreshStats() {
    showToast('Refreshing statistics...', 'info');
    location.reload();
}

function exportInvoices() {
    const params = new URLSearchParams(window.location.search);
    params.set('export_type', 'invoices');
    window.location.href = '/developer/billing/export?' + params.toString();
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
    toast.innerHTML = `<span>${message}</span><button onclick="this.parentElement.remove()" class="ml-4">&times;</button>`;
    container.appendChild(toast);
    
    setTimeout(() => toast.classList.remove('translate-x-full'), 10);
    setTimeout(() => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    const dateRangeSelect = document.querySelector('select[name="date_range"]');
    if (dateRangeSelect) toggleCustomDates(dateRangeSelect.value);
    
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            const modal = e.target.closest('.modal');
            if (modal) modal.style.display = 'none';
        }
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(modal => {
                if (modal.style.display === 'block') modal.style.display = 'none';
            });
        }
    });
    
    // Set default due date in custom invoice modal
    const customInvoiceModal = document.getElementById('customInvoiceModal');
    if (customInvoiceModal) {
        const dueDateInput = customInvoiceModal.querySelector('input[name="due_date"]');
        if (dueDateInput && !dueDateInput.value) {
            const defaultDate = new Date();
            defaultDate.setDate(defaultDate.getDate() + 30);
            dueDateInput.value = defaultDate.toISOString().split('T')[0];
        }
    }
});

// Action button styles
const style = document.createElement('style');
style.textContent = `
    .action-btn { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; border: none; cursor: pointer; }
    .action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999; }
    .modal-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(5px); }
    .modal-container { position: relative; background-color: var(--card-bg); border-radius: 16px; margin: 2rem auto; max-width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2); border: 1px solid var(--border-color); }
    .modal-header { padding: 1.5rem 1.5rem 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
    .modal-title { font-size: 1.25rem; font-weight: 600; color: var(--text-primary); margin: 0; }
    .modal-close { background: none; border: none; color: var(--text-secondary); cursor: pointer; font-size: 1.25rem; padding: 0.25rem; border-radius: 6px; }
    .modal-close:hover { background-color: rgba(var(--primary-rgb), 0.1); }
    .modal-body { padding: 1.5rem; }
    .modal-footer { padding: 1rem 1.5rem 1.5rem; border-top: 1px solid var(--border-color); display: flex; gap: 1rem; }
    @media (max-width: 768px) { .modal-footer { flex-direction: column; } .modal-container { margin: 1rem; } }
`;
document.head.appendChild(style);
</script>
@endpush

@push('styles')
<style>
.btn-secondary { padding: 0.5rem 1rem; border-radius: 8px; background-color: rgba(var(--secondary-rgb), 0.1); color: var(--text-primary); border: 1px solid rgba(var(--secondary-rgb), 0.3); transition: all 0.2s; }
.btn-secondary:hover { transform: translateY(-1px); }
.btn-primary { padding: 0.5rem 1rem; border-radius: 8px; background-color: var(--primary); color: white; border: none; transition: all 0.2s; }
.btn-primary:hover { transform: translateY(-1px); background-color: var(--secondary); }
</style>
@endpush