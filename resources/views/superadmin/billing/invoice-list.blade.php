{{-- resources/views/superadmin/billing/invoice-list.blade.php --}}
@extends('layouts.app')

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
    
    $isDarkMode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] === 'true' : false;
@endphp

@section('title', 'My Invoices')

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header -->
    <div class="card">
        <div class="flex flex-wrap justify-between items-center p-6">
            <div>
                <h1 class="text-2xl font-bold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-file-invoice mr-2" style="color: var(--primary);"></i> My Invoices
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">View and manage all your billing invoices</p>
            </div>
            <div class="flex gap-3 mt-2 md:mt-0">
                <a href="{{ route('superadmin.billing.dashboard') }}" 
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center transition-all duration-200 hover:transform hover:-translate-y-1"
                   style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <i class="fas fa-arrow-left mr-2"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="card p-5">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Total Invoices</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ $stats['total_invoices'] ?? 0 }}</h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--primary-rgb), 0.1);">
                    <i class="fas fa-file-invoice text-xl" style="color: var(--primary);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-5">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Total Amount</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--text-primary);">
                        {{ formatCurrency($stats['total_amount'] ?? 0, 'GHS') }}
                    </h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--info-rgb), 0.1);">
                    <i class="fas fa-chart-bar text-xl" style="color: var(--info);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-5">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Paid</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--success);">
                        {{ formatCurrency($stats['paid_amount'] ?? 0, 'GHS') }}
                    </h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--success-rgb), 0.1);">
                    <i class="fas fa-check-circle text-xl" style="color: var(--success);"></i>
                </div>
            </div>
        </div>
        
        <div class="card p-5">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm" style="color: var(--text-secondary);">Pending</p>
                    <h3 class="text-2xl font-bold mt-1" style="color: var(--warning);">
                        {{ formatCurrency($stats['pending_amount'] ?? 0, 'GHS') }}
                    </h3>
                </div>
                <div class="p-3 rounded-full" style="background-color: rgba(var(--warning-rgb), 0.1);">
                    <i class="fas fa-clock text-xl" style="color: var(--warning);"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card">
        <div class="p-6">
            <form method="GET" action="{{ route('superadmin.billing.invoice-list') }}" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-sm mb-1" style="color: var(--text-secondary);">Status</label>
                    <select name="status" class="rounded-lg border px-3 py-2" 
                            style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                        <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                        <option value="partial" {{ request('status') == 'partial' ? 'selected' : '' }}>Partial</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm mb-1" style="color: var(--text-secondary);">Date From</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" 
                           class="rounded-lg border px-3 py-2" style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                </div>
                
                <div>
                    <label class="block text-sm mb-1" style="color: var(--text-secondary);">Date To</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" 
                           class="rounded-lg border px-3 py-2" style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                </div>
                
                <div>
                    <label class="block text-sm mb-1" style="color: var(--text-secondary);">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" 
                           placeholder="Invoice # or description"
                           class="rounded-lg border px-3 py-2 w-48" 
                           style="border-color: var(--border-color); background-color: var(--bg-secondary); color: var(--text-primary);">
                </div>
                
                <button type="submit" class="px-4 py-2 rounded-lg text-white btn-primary">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
                
                @if(request()->anyFilled(['status', 'date_from', 'date_to', 'search']))
                <a href="{{ route('superadmin.billing.invoice-list') }}" 
                   class="px-4 py-2 rounded-lg" 
                   style="background-color: var(--bg-secondary); color: var(--text-secondary); border: 1px solid var(--border-color);">
                    <i class="fas fa-times mr-2"></i> Clear
                </a>
                @endif
            </form>
        </div>
    </div>

    <!-- Invoices Table -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-list mr-2" style="color: var(--primary);"></i> All Invoices
                </h3>
                <span class="text-sm" style="color: var(--text-secondary);">{{ $invoices->total() }} invoices found</span>
            </div>
        </div>
        <div class="p-6">
            @if($invoices->isEmpty())
            <div class="text-center py-8" style="color: var(--text-secondary);">
                <i class="fas fa-file-invoice text-4xl mb-3 opacity-50"></i>
                <p class="text-lg">No invoices found</p>
                <p class="text-sm mt-1">You don't have any invoices yet.</p>
            </div>
            @else
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr style="background-color: var(--bg-secondary);">
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Invoice</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Date</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Due Date</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Amount</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Status</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $invoice)
                        <tr class="border-t hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors" style="border-color: var(--border-color);">
                            <td class="p-3">
                                <div>
                                    <a href="{{ route('superadmin.billing.invoice-view', $invoice->id) }}" 
                                       class="font-medium hover:text-primary transition-colors" style="color: var(--text-primary);">
                                        #{{ $invoice->invoice_number }}
                                    </a>
                                    @if($invoice->is_primary_invoice ?? false)
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs badge-primary">
                                        <i class="fas fa-crown mr-1"></i> Primary
                                    </span>
                                    @endif
                                    <div class="text-sm mt-1" style="color: var(--text-secondary); max-width: 200px;">
                                        {{ Str::limit($invoice->description, 40) }}
                                    </div>
                                </div>
                            </td>
                            <td class="p-3" style="color: var(--text-secondary);">
                                {{ $invoice->issue_date ? $invoice->issue_date->format('M d, Y') : 'N/A' }}
                            </td>
                            <td class="p-3" style="color: var(--text-secondary);">
                                {{ $invoice->due_date ? $invoice->due_date->format('M d, Y') : 'N/A' }}
                                @if($invoice->due_date && $invoice->due_date->isPast() && $invoice->status !== 'paid')
                                <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs badge-danger">
                                    Overdue
                                </span>
                                @endif
                            </td>
                            <td class="p-3">
                                <span class="font-medium" style="color: var(--text-primary);">
                                    {{ formatCurrency($invoice->amount, $invoice->currency ?? 'GHS') }}
                                </span>
                            </td>
                            <td class="p-3">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium badge-{{ getStatusColor($invoice->status) }}">
                                    <i class="fas fa-{{ $invoice->status === 'paid' ? 'check-circle' : ($invoice->status === 'overdue' ? 'exclamation-circle' : 'clock') }} mr-1"></i>
                                    {{ ucfirst($invoice->status) }}
                                </span>
                            </td>
                            <td class="p-3">
    <div class="flex gap-2 flex-wrap">
        <!-- View Button -->
        <a href="{{ route('superadmin.billing.invoice-view', $invoice->id) }}" 
           class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
            <i class="fas fa-eye mr-1"></i> View
        </a>
        
        <!-- ✅ PAY BUTTON - Fixed -->
        @php
            // Get agreement ID from multiple possible sources
            $agreementId = null;
            if (isset($invoice->admin_billing_record_id) && $invoice->admin_billing_record_id) {
                $agreementId = $invoice->admin_billing_record_id;
            } elseif (isset($invoice->agreement_id) && $invoice->agreement_id) {
                $agreementId = $invoice->agreement_id;
            } elseif (isset($invoice->agreement) && $invoice->agreement) {
                $agreementId = $invoice->agreement->id;
            }
        @endphp
        
        @if($invoice->status !== 'paid' && $agreementId)
        <a href="{{ route('superadmin.billing.record-payment-form', $agreementId) }}" 
           class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center text-white btn-primary">
            <i class="fas fa-credit-card mr-1"></i> Pay Now
        </a>
        @endif
        
        <!-- ✅ PAID STATUS - Show if paid -->
        @if($invoice->status === 'paid')
        <span class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
              style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
            <i class="fas fa-check-circle mr-1"></i> Paid
        </span>
        @endif
        
        <!-- Download Button (if file exists) -->
        @if($invoice->hasFile ?? false)
        <a href="{{ route('superadmin.billing.invoice-download', $invoice->id) }}" 
           class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center"
           style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
            <i class="fas fa-download mr-1"></i> PDF
        </a>
        @endif
    </div>
</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="mt-4">
                {{ $invoices->links() }}
            </div>
            @endif
        </div>
    </div>
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

@media (max-width: 768px) {
    .grid-cols-1.md\:grid-cols-2.lg\:grid-cols-4 {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 480px) {
    .grid-cols-1.md\:grid-cols-2.lg\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
}
</style>
@endpush