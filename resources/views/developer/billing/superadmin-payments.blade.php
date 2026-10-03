{{-- resources/views/developer/billing/superadmin-payments.blade.php --}}
@extends('layouts.' . (auth()->user()->type === \App\Models\User::TYPE_FIELD_AGENT ? 'field' : 'dev'))

@php
    $pageTitle = 'Super Admin Payments';

    if (!function_exists('formatCurrency')) {
        function formatCurrency($amount, $currency = 'GHS') {
            if (empty($amount)) return 'GH₵0.00';
            if ($currency === 'GHS') {
                return 'GH₵' . number_format((float)$amount, 2);
            }
            return $currency . ' ' . number_format((float)$amount, 2);
        }
    }

    // Controller-provided variables
    $paymentRecords  = $paymentRecords  ?? collect();
    $stats           = $stats           ?? ['total_paid' => 0, 'total_records' => 0, 'unique_months' => 0];
    $superAdmins     = $superAdmins     ?? collect();
    $availableMonths = $availableMonths ?? collect();

    $isDarkMode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] === 'true' : false;
@endphp

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- Header Card --}}
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-3">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-user-shield text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-user-shield mr-2" style="color: var(--primary);"></i>
                        Super Admin Payments
                    </h2>
                    <div class="text-sm flex items-center mt-1 flex-wrap gap-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle"></i>
                        <span>Track payments received from super admins</span>
                        <span class="mx-1">•</span>
                        <i class="fas fa-circle" style="color: var(--success); font-size: 0.5rem;"></i>
                        <span class="font-medium">{{ number_format($stats['total_records'] ?? 0) }} records</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('developer.billing.dashboard') }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-medium inline-flex items-center transition-all duration-200 hover:transform hover:-translate-y-0.5"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Dashboard
                </a>
                <a href="{{ route('developer.billing.create-super-admin-request-form') }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-plus mr-1"></i> New Payment Request
                </a>
            </div>
        </div>
    </div>

    {{-- Flash Messages --}}
    @foreach(['success' => 'check-circle', 'error' => 'exclamation-circle', 'warning' => 'exclamation-triangle', 'info' => 'info-circle'] as $type => $icon)
        @if(session($type))
        <div class="card">
            <div class="flex items-center p-4"
                 style="background-color: rgba(var(--{{ $type === 'error' ? 'danger' : $type }}-rgb), 0.1);
                        border: 1px solid rgba(var(--{{ $type === 'error' ? 'danger' : $type }}-rgb), 0.3);
                        border-radius: 12px;">
                <div class="flex-shrink-0">
                    <i class="fas fa-{{ $icon }} text-xl" style="color: var(--{{ $type === 'error' ? 'danger' : $type }});"></i>
                </div>
                <div class="ml-3 flex-1">
                    <p style="color: var(--{{ $type === 'error' ? 'danger' : $type }}); font-weight: 500;">{{ session($type) }}</p>
                </div>
                <button type="button" onclick="this.closest('.card').remove()" class="ml-auto">
                    <i class="fas fa-times" style="color: var(--text-secondary);"></i>
                </button>
            </div>
        </div>
        @endif
    @endforeach

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="card stat-card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                     style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                    <i class="fas fa-coins text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Paid</p>
                    <p class="text-2xl font-bold" style="color: var(--success);">
                        {{ formatCurrency($stats['total_paid'] ?? 0) }}
                    </p>
                </div>
            </div>
        </div>

        <div class="card stat-card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    <i class="fas fa-list-alt text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Records</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ number_format($stats['total_records'] ?? 0) }}
                    </p>
                </div>
            </div>
        </div>

        <div class="card stat-card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                     style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                    <i class="fas fa-calendar-check text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Unique Months</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ number_format($stats['unique_months'] ?? 0) }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters Card --}}
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Filter Payments
        </h3>

        <form method="GET" action="{{ route('developer.billing.super-admin-payments') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Super Admin</label>
                    <select name="super_admin_id"
                            class="form-select w-full p-2.5 rounded-lg border"
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                        <option value="">All Super Admins</option>
                        @foreach($superAdmins as $admin)
                            <option value="{{ $admin->id }}" {{ request('super_admin_id') == $admin->id ? 'selected' : '' }}>
                                {{ $admin->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Billing Month</label>
                    <select name="billing_month"
                            class="form-select w-full p-2.5 rounded-lg border"
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                        <option value="">All Months</option>
                        @foreach($availableMonths as $month)
                            <option value="{{ $month }}" {{ request('billing_month') == $month ? 'selected' : '' }}>
                                {{ $month }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Per Page</label>
                    <select name="per_page"
                            class="form-select w-full p-2.5 rounded-lg border"
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                        @foreach([10, 20, 50, 100] as $n)
                            <option value="{{ $n }}" {{ request('per_page', 20) == $n ? 'selected' : '' }}>{{ $n }} per page</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit"
                            class="flex-1 px-4 py-2 rounded-lg font-medium inline-flex items-center justify-center text-white btn-primary">
                        <i class="fas fa-filter mr-2"></i> Apply
                    </button>
                    <a href="{{ route('developer.billing.super-admin-payments') }}"
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    {{-- Payment Records Table --}}
    <div class="card p-6">
        <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-table mr-2" style="color: var(--secondary);"></i> Payment Records
            </h3>
            <div class="text-sm" style="color: var(--text-secondary);">
                @if($paymentRecords->count() > 0)
                    Showing {{ $paymentRecords->firstItem() }} - {{ $paymentRecords->lastItem() }} of {{ $paymentRecords->total() }}
                @else
                    No records
                @endif
            </div>
        </div>

        @if($paymentRecords->isEmpty())
            <div class="text-center py-12">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full mb-4"
                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-inbox text-2xl"></i>
                </div>
                <h3 class="text-lg font-medium mb-2" style="color: var(--text-primary);">No Payment Records Found</h3>
                <p class="mb-6 max-w-md mx-auto" style="color: var(--text-secondary);">
                    @if(request()->hasAny(['super_admin_id', 'billing_month']))
                        Try adjusting your filters to see more results.
                    @else
                        Payment records will appear here once super admins start paying.
                    @endif
                </p>
                @if(request()->hasAny(['super_admin_id', 'billing_month']))
                    <a href="{{ route('developer.billing.super-admin-payments') }}"
                       class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                       style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                        <i class="fas fa-redo mr-2"></i> Clear Filters
                    </a>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table payments-table w-full">
                    <thead>
                        <tr>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Date</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Super Admin</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Agreement</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Billing Month</th>
                            <th class="text-right p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Amount Paid</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Method</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Reference</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($paymentRecords as $record)
                            @php
                                $statusColors = [
                                    'confirmed' => ['bg' => 'rgba(var(--success-rgb), 0.1)', 'text' => 'var(--success)', 'border' => 'rgba(var(--success-rgb), 0.3)', 'icon' => 'fa-check-circle'],
                                    'pending'   => ['bg' => 'rgba(var(--warning-rgb), 0.1)', 'text' => 'var(--warning)', 'border' => 'rgba(var(--warning-rgb), 0.3)', 'icon' => 'fa-clock'],
                                    'failed'    => ['bg' => 'rgba(var(--danger-rgb), 0.1)',  'text' => 'var(--danger)',  'border' => 'rgba(var(--danger-rgb), 0.3)',  'icon' => 'fa-times-circle'],
                                ];
                                $s = $statusColors[$record->status] ?? $statusColors['pending'];

                                $methodLabel = match($record->payment_method) {
                                    'bank_transfer' => 'Bank Transfer',
                                    'mobile_money', 'mtn', 'telecel', 'airteltigo' => 'Mobile Money',
                                    'cash'  => 'Cash',
                                    'check' => 'Check',
                                    'paystack'    => 'Paystack',
                                    'expresspay'  => 'ExpressPay',
                                    'flutterwave' => 'Flutterwave',
                                    'hubtel'      => 'Hubtel',
                                    default       => ucfirst(str_replace('_', ' ', $record->payment_method ?? 'Unknown')),
                                };
                            @endphp
                            <tr>
                                <td class="p-3 border-b" style="border-color: var(--border-color); color: var(--text-secondary);">
                                    {{ $record->payment_date ? \Carbon\Carbon::parse($record->payment_date)->format('M d, Y') : '—' }}
                                </td>
                                <td class="p-3 border-b" style="border-color: var(--border-color);">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2 flex-shrink-0"
                                             style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                            <i class="fas fa-user-shield text-xs"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-sm font-medium truncate" style="color: var(--text-primary);">
                                                {{ $record->superAdmin->name ?? 'N/A' }}
                                            </div>
                                            <div class="text-xs truncate" style="color: var(--text-secondary);">
                                                {{ $record->superAdmin->email ?? '' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-3 border-b" style="border-color: var(--border-color);">
                                    <span class="font-mono text-sm" style="color: var(--text-primary);">
                                        {{ $record->agreement->agreement_number ?? '—' }}
                                    </span>
                                </td>
                                <td class="p-3 border-b" style="border-color: var(--border-color); color: var(--text-secondary);">
                                    {{ $record->billing_month ?? '—' }}
                                </td>
                                <td class="p-3 border-b font-medium text-right" style="border-color: var(--border-color); color: var(--success);">
                                    {{ formatCurrency($record->amount_paid ?? 0, $record->currency ?? 'GHS') }}
                                </td>
                                <td class="p-3 border-b" style="border-color: var(--border-color);">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-info">
                                        {{ $methodLabel }}
                                    </span>
                                </td>
                                <td class="p-3 border-b" style="border-color: var(--border-color); color: var(--text-secondary);">
                                    <span class="font-mono text-xs">{{ $record->transaction_reference ?? '—' }}</span>
                                </td>
                                <td class="p-3 border-b" style="border-color: var(--border-color);">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                          style="background-color: {{ $s['bg'] }}; color: {{ $s['text'] }}; border: 1px solid {{ $s['border'] }};">
                                        <i class="fas {{ $s['icon'] }} mr-1"></i>
                                        {{ ucfirst($record->status) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($paymentRecords->hasPages())
            <div class="mt-6 pt-4" style="border-top: 1px solid var(--border-color);">
                {{ $paymentRecords->withQueryString()->links() }}
            </div>
            @endif
        @endif
    </div>
</div>

{{-- Toast Container --}}
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@section('scripts')
<script>
function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(container);
    }

    const colors = {
        success: { bg: 'bg-green-100',  text: 'text-green-800',  icon: 'fa-check-circle' },
        error:   { bg: 'bg-red-100',    text: 'text-red-800',    icon: 'fa-exclamation-circle' },
        warning: { bg: 'bg-yellow-100', text: 'text-yellow-800', icon: 'fa-exclamation-triangle' },
        info:    { bg: 'bg-blue-100',   text: 'text-blue-800',   icon: 'fa-info-circle' },
    };
    const cfg = colors[type] || colors.info;

    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg flex items-center justify-between min-w-64 max-w-md transform transition-all duration-300 translate-x-full ${cfg.bg} ${cfg.text}`;

    const messageEl = document.createElement('span');
    messageEl.className = 'text-sm font-medium flex-1';
    messageEl.innerHTML = `<i class="fas ${cfg.icon} mr-2"></i>${message}`;

    const closeBtn = document.createElement('button');
    closeBtn.className = 'ml-4 transition-colors duration-200 hover:opacity-70';
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.onclick = () => {
        toast.classList.add('translate-x-full');
        setTimeout(() => toast.remove(), 300);
    };

    toast.appendChild(messageEl);
    toast.appendChild(closeBtn);
    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.remove('translate-x-full');
        toast.classList.add('translate-x-0');
    }, 10);

    setTimeout(() => {
        if (toast.parentNode === container) {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

document.addEventListener('DOMContentLoaded', function() {
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
@endsection

@section('styles')
<style>
/* Card + stat card */
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06) !important;
}

.stat-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
}

/* Table hover — primary tint, no white flash */
.payments-table tbody tr {
    background-color: transparent !important;
    transition: background-color 0.15s ease !important;
}

.payments-table tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.04) !important;
}

html.dark .payments-table tbody tr:hover,
body.dark .payments-table tbody tr:hover,
body[data-theme="dark"] .payments-table tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.08) !important;
}

.payments-table tbody tr:hover > td {
    border-color: var(--border-color) !important;
}

/* Badges */
.badge-info {
    background-color: rgba(var(--info-rgb), 0.1) !important;
    color: var(--info) !important;
    border: 1px solid rgba(var(--info-rgb), 0.3) !important;
}

/* Form controls */
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

/* Primary button */
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

/* Responsive */
@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-3 { grid-template-columns: 1fr; }
    .grid.grid-cols-1.md\:grid-cols-4 { grid-template-columns: 1fr; }
    .table { font-size: 0.75rem; }
    .table th, .table td { padding: 0.5rem !important; }
    .card .p-6 { padding: 1rem !important; }
    .text-2xl { font-size: 1.25rem !important; }
}
</style>
@endsection