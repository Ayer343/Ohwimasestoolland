{{-- resources/views/superadmin/billing/invoice-details.blade.php --}}
@extends('layouts.app')

@section('title', 'Invoice #' . $invoice->invoice_number)

@php
    function formatCurrency($amount, $currency = 'GHS') {
        if (empty($amount)) return 'GH₵0.00';
        if ($currency === 'GHS') {
            return 'GH₵' . number_format($amount, 2);
        }
        return $currency . ' ' . number_format($amount, 2);
    }
    
    function getStatusColor($status) {
        $colors = [
            'paid' => 'success',
            'pending' => 'warning',
            'overdue' => 'danger',
            'cancelled' => 'secondary',
            'partial' => 'info'
        ];
        return $colors[$status] ?? 'secondary';
    }
    
    function getStatusIcon($status) {
        $icons = [
            'paid' => 'fa-check-circle',
            'pending' => 'fa-clock',
            'overdue' => 'fa-exclamation-circle',
            'cancelled' => 'fa-times-circle',
            'partial' => 'fa-hourglass-half'
        ];
        return $icons[$status] ?? 'fa-circle';
    }
    
    $isDarkMode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] === 'true' : false;
    
    // Determine if invoice is overdue
    $isOverdue = $invoice->due_date && $invoice->due_date->isPast() && $invoice->status !== 'paid';
    
    // Determine if invoice can be paid online
    $canPayOnline = $invoice->status !== 'paid' && isset($agreement);
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Breadcrumb -->
    <div class="card p-4">
        <nav class="text-sm" style="color: var(--text-secondary);">
            <a href="{{ route('superadmin.billing.dashboard') }}" class="hover:text-primary transition-colors">Dashboard</a>
            <span class="mx-2 text-xs">/</span>
            <a href="{{ route('superadmin.billing.invoice-list') }}" class="hover:text-primary transition-colors">Invoices</a>
            <span class="mx-2 text-xs">/</span>
            <span style="color: var(--text-primary); font-weight: 500;">#{{ $invoice->invoice_number }}</span>
        </nav>
    </div>

    <!-- Header Card -->
    <div class="card">
        <div class="flex flex-wrap justify-between items-center p-6">
            <div>
                <div class="flex items-center gap-3">
                    <div class="w-14 h-14 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-color: var(--primary);">
                        <i class="fas fa-file-invoice text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                            Invoice #{{ $invoice->invoice_number }}
                        </h2>
                        <p class="text-sm mt-0.5" style="color: var(--text-secondary);">{{ $invoice->description }}</p>
                        @if($isPrimaryInvoice ?? false)
                        <span class="inline-flex items-center mt-1 px-3 py-1 rounded-full text-xs font-medium badge-primary">
                            <i class="fas fa-crown mr-1"></i> Primary Billing Invoice
                        </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3 mt-3 md:mt-0 flex-wrap">
                <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium badge-{{ getStatusColor($invoice->status) }}">
                    <i class="fas {{ getStatusIcon($invoice->status) }} mr-2"></i>
                    {{ ucfirst($invoice->status) }}
                    @if($isOverdue)
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium badge-danger">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Overdue
                    </span>
                    @endif
                </span>
                
                @if($canPayOnline)
                <a href="{{ route('superadmin.billing.record-payment-form', $agreement->id) }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-credit-card mr-2"></i> Pay Now
                </a>
                @endif
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Invoice Details -->
        <div class="card lg:col-span-2">
            <div class="p-6 border-b" style="border-color: var(--border-color); background: linear-gradient(to right, var(--bg-secondary), rgba(var(--primary-rgb), 0.03));">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-2" style="color: var(--primary);"></i> Invoice Information
                </h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.03);">
                            <label class="block text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Invoice Number</label>
                            <div class="font-mono font-semibold" style="color: var(--text-primary);">#{{ $invoice->invoice_number }}</div>
                        </div>
                        
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.03);">
                            <label class="block text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Issue Date</label>
                            <div style="color: var(--text-primary);">{{ $invoice->issue_date ? $invoice->issue_date->format('F j, Y') : 'N/A' }}</div>
                        </div>
                        
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.03);">
                            <label class="block text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Due Date</label>
                            <div style="color: var(--text-primary);">{{ $invoice->due_date ? $invoice->due_date->format('F j, Y') : 'N/A' }}</div>
                            @if($isOverdue)
                            <span class="inline-flex items-center mt-1 px-2 py-0.5 rounded-full text-xs font-medium badge-danger">
                                <i class="fas fa-exclamation-triangle mr-1"></i> Overdue
                            </span>
                            @endif
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.03);">
                            <label class="block text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Invoice Type</label>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium badge-info">
                                {{ ucfirst($invoice->invoice_type ?? 'Standard') }}
                            </span>
                        </div>
                        
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.03);">
                            <label class="block text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Amount</label>
                            <div class="text-2xl font-bold" style="color: var(--text-primary);">
                                {{ formatCurrency($invoice->amount, $invoice->currency ?? 'GHS') }}
                            </div>
                        </div>
                        
                        @if($invoice->billing_month)
                        <div class="p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.03);">
                            <label class="block text-xs font-medium uppercase tracking-wider mb-1" style="color: var(--text-secondary);">Billing Month</label>
                            <div style="color: var(--text-primary);">{{ \Carbon\Carbon::parse($invoice->billing_month . '-01')->format('F Y') }}</div>
                        </div>
                        @endif
                    </div>
                </div>
                
                <!-- Payment Summary -->
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="text-center p-3 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05);">
                            <div class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Total Amount</div>
                            <div class="text-xl font-bold mt-1" style="color: var(--text-primary);">
                                {{ formatCurrency($invoice->amount, $invoice->currency ?? 'GHS') }}
                            </div>
                        </div>
                        <div class="text-center p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05);">
                            <div class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Amount Paid</div>
                            <div class="text-xl font-bold mt-1" style="color: var(--success);">
                                {{ formatCurrency($totalPaid, $invoice->currency ?? 'GHS') }}
                            </div>
                        </div>
                        <div class="text-center p-3 rounded-lg" style="background-color: rgba(var(--danger-rgb), 0.05);">
                            <div class="text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Remaining Balance</div>
                            <div class="text-xl font-bold mt-1" style="color: var(--danger);">
                                {{ formatCurrency($remaining, $invoice->currency ?? 'GHS') }}
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Progress -->
                <div class="mt-4">
                    <div class="flex justify-between text-sm mb-1" style="color: var(--text-secondary);">
                        <span>Payment Progress</span>
                        <span>{{ $invoice->amount > 0 ? number_format(($totalPaid / $invoice->amount) * 100, 1) : 0 }}% Paid</span>
                    </div>
                    <div class="w-full rounded-full h-2.5" style="background-color: rgba(var(--secondary-rgb), 0.2); overflow: hidden;">
                        <div class="h-2.5 rounded-full transition-all duration-500" 
                             style="width: {{ $invoice->amount > 0 ? min(($totalPaid / $invoice->amount) * 100, 100) : 0 }}%; 
                                    background: linear-gradient(90deg, var(--primary) 0%, var(--success) 100%);">
                        </div>
                    </div>
                </div>
                
                <!-- Agreement Details -->
                @if(isset($agreement))
                <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                    <label class="block text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        <i class="fas fa-file-contract mr-1"></i> Agreement Details
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="flex justify-between p-2 rounded-lg" style="background-color: var(--bg-secondary);">
                            <span style="color: var(--text-secondary);">Agreement</span>
                            <a href="{{ route('superadmin.billing.view-agreement', $agreement->id) }}" 
                               class="font-medium hover:text-primary transition-colors" style="color: var(--primary);">
                                {{ $agreement->agreement_number }}
                            </a>
                        </div>
                        <div class="flex justify-between p-2 rounded-lg" style="background-color: var(--bg-secondary);">
                            <span style="color: var(--text-secondary);">Description</span>
                            <span style="color: var(--text-primary);">{{ Str::limit($agreement->description, 40) }}</span>
                        </div>
                        @if($isPrimaryInvoice ?? false)
                        <div class="flex justify-between p-2 rounded-lg" style="background-color: var(--bg-secondary);">
                            <span style="color: var(--text-secondary);">Role</span>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-primary">
                                <i class="fas fa-crown mr-1"></i> Primary Contact
                            </span>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Right Column -->
        <div class="space-y-6">
            <!-- Payment Method Details -->
            @if(isset($paymentMethodDetails))
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color); background: linear-gradient(to right, var(--bg-secondary), rgba(var(--info-rgb), 0.03));">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-credit-card mr-2" style="color: var(--primary);"></i> Payment Method
                    </h3>
                </div>
                <div class="p-6 space-y-3">
                    <div class="flex justify-between p-2 rounded-lg" style="background-color: var(--bg-secondary);">
                        <span style="color: var(--text-secondary);">Method</span>
                        <span style="color: var(--text-primary); font-weight: 500;">{{ $paymentMethodDetails['method_label'] ?? 'N/A' }}</span>
                    </div>
                    @if(isset($paymentMethodDetails['display_text']) && $paymentMethodDetails['display_text'])
                    <div class="flex justify-between p-2 rounded-lg" style="background-color: var(--bg-secondary);">
                        <span style="color: var(--text-secondary);">Details</span>
                        <span style="color: var(--text-primary);">{{ $paymentMethodDetails['display_text'] }}</span>
                    </div>
                    @endif
                    @if(isset($paymentMethodDetails['fields']) && is_array($paymentMethodDetails['fields']))
                        @foreach($paymentMethodDetails['fields'] as $label => $value)
                            @if($value)
                            <div class="flex justify-between p-2 rounded-lg" style="background-color: var(--bg-secondary);">
                                <span style="color: var(--text-secondary);">{{ $label }}</span>
                                <span style="color: var(--text-primary);">{{ $value }}</span>
                            </div>
                            @endif
                        @endforeach
                    @endif
                </div>
            </div>
            @endif

            <!-- Signature Status -->
            @if(isset($signatureStatus))
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color); background: linear-gradient(to right, var(--bg-secondary), rgba(var(--success-rgb), 0.03));">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-signature mr-2" style="color: var(--primary);"></i> Signature Status
                    </h3>
                </div>
                <div class="p-6 space-y-3">
                    <div class="flex justify-between items-center p-3 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <span style="color: var(--text-secondary);">Developer</span>
                        @if($signatureStatus['developer_signed'])
                        <span style="color: var(--success);">
                            <i class="fas fa-check-circle mr-1"></i> Signed
                        </span>
                        @else
                        <span style="color: var(--warning);">
                            <i class="fas fa-clock mr-1"></i> Pending
                        </span>
                        @endif
                    </div>
                    <div class="flex justify-between items-center p-3 rounded-lg" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <span style="color: var(--text-secondary);">Super Admin</span>
                        @if($signatureStatus['super_admin_signed'])
                        <span style="color: var(--success);">
                            <i class="fas fa-check-circle mr-1"></i> Signed
                        </span>
                        @else
                        <span style="color: var(--warning);">
                            <i class="fas fa-clock mr-1"></i> Pending
                        </span>
                        @endif
                    </div>
                    @if($signatureStatus['all_signed'])
                    <div class="p-3 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2" style="color: var(--success);"></i>
                            <span style="color: var(--text-primary); font-weight: 500;">All parties have signed</span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Quick Actions -->
            <div class="card">
                <div class="p-6 border-b" style="border-color: var(--border-color); background: linear-gradient(to right, var(--bg-secondary), rgba(var(--warning-rgb), 0.03));">
                    <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-bolt mr-2" style="color: var(--warning);"></i> Quick Actions
                    </h3>
                </div>
                <div class="p-6 space-y-3">
                    @if($invoiceFile)
                    <a href="{{ route('superadmin.billing.invoice-download', $invoice->id) }}" 
                       class="flex items-center p-3 rounded-lg border transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md w-full"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <div class="p-2 rounded-lg mr-3" style="background-color: rgba(var(--primary-rgb), 0.1);">
                            <i class="fas fa-file-pdf" style="color: var(--danger);"></i>
                        </div>
                        <div class="flex-1">
                            <div style="color: var(--text-primary); font-weight: 500;">Download PDF</div>
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">Download invoice as PDF</div>
                        </div>
                        <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                    </a>
                    @endif
                    
                    <a href="{{ route('superadmin.billing.invoice-list') }}" 
                       class="flex items-center p-3 rounded-lg border transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md w-full"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <div class="p-2 rounded-lg mr-3" style="background-color: rgba(var(--info-rgb), 0.1);">
                            <i class="fas fa-list" style="color: var(--info);"></i>
                        </div>
                        <div class="flex-1">
                            <div style="color: var(--text-primary); font-weight: 500;">Back to Invoices</div>
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">View all invoices</div>
                        </div>
                        <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                    </a>
                    
                    @if($canPayOnline)
                    <a href="{{ route('superadmin.billing.record-payment-form', $agreement->id) }}" 
                       class="flex items-center p-3 rounded-lg border transition-all duration-200 hover:transform hover:-translate-y-1 hover:shadow-md w-full"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <div class="p-2 rounded-lg mr-3" style="background-color: rgba(var(--success-rgb), 0.1);">
                            <i class="fas fa-credit-card" style="color: var(--success);"></i>
                        </div>
                        <div class="flex-1">
                            <div style="color: var(--text-primary); font-weight: 500;">Make Payment</div>
                            <div class="text-xs mt-1" style="color: var(--text-secondary);">Pay this invoice</div>
                        </div>
                        <i class="fas fa-chevron-right" style="color: var(--text-secondary);"></i>
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Line Items -->
    @if(isset($invoice->items) && is_array($invoice->items) && count($invoice->items) > 0)
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color); background: linear-gradient(to right, var(--bg-secondary), rgba(var(--secondary-rgb), 0.03));">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-list-ul mr-2" style="color: var(--secondary);"></i> Line Items
            </h3>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr style="background-color: var(--bg-secondary); border-bottom: 2px solid var(--border-color);">
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Description</th>
                            <th class="text-right p-3 text-sm font-medium" style="color: var(--text-secondary);">Quantity</th>
                            <th class="text-right p-3 text-sm font-medium" style="color: var(--text-secondary);">Unit Price</th>
                            <th class="text-right p-3 text-sm font-medium" style="color: var(--text-secondary);">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->items as $item)
                        <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-800" style="border-color: var(--border-color);">
                            <td class="p-3" style="color: var(--text-primary);">{{ $item['description'] ?? 'N/A' }}</td>
                            <td class="p-3 text-right" style="color: var(--text-primary);">{{ $item['quantity'] ?? 1 }}</td>
                            <td class="p-3 text-right" style="color: var(--text-primary);">
                                {{ $invoice->currency ?? 'GHS' }} {{ number_format($item['unit_price'] ?? 0, 2) }}
                            </td>
                            <td class="p-3 text-right font-medium" style="color: var(--text-primary);">
                                {{ $invoice->currency ?? 'GHS' }} {{ number_format($item['total'] ?? 0, 2) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="background-color: var(--bg-secondary); border-top: 2px solid var(--border-color);">
                            <td colspan="3" class="p-3 text-right font-bold" style="color: var(--text-secondary);">Total</td>
                            <td class="p-3 text-right font-bold" style="color: var(--text-primary);">
                                {{ $invoice->currency ?? 'GHS' }} {{ number_format($invoice->amount, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- Payment History -->
    @if(isset($payments) && $payments->isNotEmpty())
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color); background: linear-gradient(to right, var(--bg-secondary), rgba(var(--info-rgb), 0.03));">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-history mr-2" style="color: var(--info);"></i> Payment History
            </h3>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr style="background-color: var(--bg-secondary); border-bottom: 2px solid var(--border-color);">
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Date</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Amount</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Method</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Reference</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $payment)
                        <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-800" style="border-color: var(--border-color);">
                            <td class="p-3" style="color: var(--text-secondary);">
                                {{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}
                            </td>
                            <td class="p-3 font-medium" style="color: var(--text-primary);">
                                {{ $invoice->currency ?? 'GHS' }} {{ number_format($payment->amount_paid, 2) }}
                            </td>
                            <td class="p-3">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-info">
                                    {{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}
                                </span>
                            </td>
                            <td class="p-3" style="color: var(--text-secondary);">
                                {{ $payment->transaction_reference ?? 'N/A' }}
                            </td>
                            <td class="p-3">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-success">
                                    <i class="fas fa-check-circle mr-1"></i> Confirmed
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="background-color: var(--bg-secondary); border-top: 2px solid var(--border-color);">
                            <td colspan="1" class="p-3 font-bold" style="color: var(--text-secondary);">Total Paid</td>
                            <td class="p-3 font-bold" style="color: var(--success);">
                                {{ $invoice->currency ?? 'GHS' }} {{ number_format($totalPaid, 2) }}
                            </td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
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

document.addEventListener('DOMContentLoaded', function() {
    @if(session('success'))
        showToast('{{ session('success') }}', 'success');
    @endif
    
    @if(session('error'))
        showToast('{{ session('error') }}', 'error');
    @endif
    
    @if(session('warning'))
        showToast('{{ session('warning') }}', 'warning');
    @endif
    
    @if($isOverdue)
        showToast('This invoice is overdue. Please make a payment as soon as possible.', 'warning');
    @endif
});
</script>
@endpush

@push('styles')
<style>
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
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
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

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

.table {
    width: 100%;
    border-collapse: collapse;
}

.table tr:hover {
    background-color: rgba(var(--primary-rgb), 0.02) !important;
}

.tool-link {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    transition: all 0.2s ease !important;
    cursor: pointer;
}

.tool-link:hover {
    border-color: var(--primary) !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(var(--primary-rgb), 0.1);
}

.tool-link:hover span {
    color: var(--primary) !important;
}

.tool-link:hover .fas.fa-chevron-right {
    color: var(--primary) !important;
    transform: translateX(2px);
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.card {
    animation: fadeIn 0.4s ease forwards;
}

@media (max-width: 768px) {
    .grid.grid-cols-1.lg\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    .grid.grid-cols-1.md\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    .grid.grid-cols-1.md\:grid-cols-3 {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 480px) {
    .grid.grid-cols-1.md\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
}
</style>
@endpush