{{-- resources/views/superadmin/billing/reports.blade.php --}}
@extends('layouts.app')

@php
    $pageTitle = 'Billing Reports';
    
    function formatCurrency($amount, $currency = 'GHS') {
        if (empty($amount)) return 'GH₵0.00';
        if ($currency === 'GHS') {
            return 'GH₵' . number_format($amount, 2);
        }
        return $currency . ' ' . number_format($amount, 2);
    }
    
    // Theme detection
    $isDarkMode = isset($_COOKIE['dark_mode']) ? $_COOKIE['dark_mode'] === 'true' : false;
    
    // Calculate summary stats
    $totalPayments = $payments->sum('amount_paid');
    $totalAgreements = $agreements->count();
@endphp

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Header Card -->
    <div class="card">
        <div class="flex justify-between items-center p-6">
            <div class="flex items-center">
                <!-- Icon -->
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-chart-bar text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i> 
                        Billing Reports
                    </h2>
                    <div class="text-sm flex items-center mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span>Detailed billing analysis and financial reports</span>
                        <span class="mx-2">•</span>
                        <i class="fas fa-calendar-alt mr-2"></i>
                        <span>{{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}</span>
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
                    <button onclick="exportReport()" 
                            class="px-3 py-1 rounded-lg text-xs font-medium inline-flex items-center" 
                            style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                        <i class="fas fa-download mr-1"></i> Export Report
                    </button>
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
                
                <a href="{{ route('superadmin.billing.agreements-list') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-list-alt mr-1"></i> All Agreements
                </a>
                
                <a href="{{ route('superadmin.billing.history') }}" 
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium" 
                   style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-history mr-1"></i> Payment History
                </a>
            </div>
        </div>
    </div>

    <!-- Date Range Filter Card -->
    <div class="card p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Report Period
        </h3>
        
        <form method="GET" action="{{ route('superadmin.billing.reports') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        Start Date *
                    </label>
                    <input type="date" 
                           name="start_date" 
                           value="{{ $startDate }}" 
                           class="form-input w-full p-3 rounded-lg border"
                           style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                           required>
                </div>
                
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">
                        End Date *
                    </label>
                    <input type="date" 
                           name="end_date" 
                           value="{{ $endDate }}" 
                           class="form-input w-full p-3 rounded-lg border"
                           style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);"
                           required>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" 
                            class="px-4 py-3 rounded-lg font-medium inline-flex items-center text-white btn-primary w-full">
                        <i class="fas fa-chart-bar mr-2"></i> Generate Report
                    </button>
                </div>
            </div>
            
            <div class="text-xs" style="color: var(--text-secondary);">
                <i class="fas fa-info-circle mr-1"></i> Select date range to generate custom reports
            </div>
        </form>
    </div>

    <!-- Summary Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="card stat-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-file-contract text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Agreements</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ $stats['total_agreements'] }}</p>
                </div>
            </div>
        </div>
        
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
            </div>
        </div>
        
        <div class="card stat-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-money-check-alt text-lg"></i>
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
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-money-bill-wave text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Paid</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ formatCurrency($stats['total_amount_paid']) }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Reports Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Monthly Breakdown -->
        <div class="card">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-calendar-alt mr-2" style="color: var(--primary);"></i> Monthly Payment Breakdown
                </h3>
            </div>
            <div class="p-6">
                @if(count($monthlyBreakdown) > 0)
                <div class="space-y-4">
                    @foreach($monthlyBreakdown as $month)
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span style="color: var(--text-primary);">{{ $month['month'] }}</span>
                            <span style="color: var(--text-secondary);">{{ formatCurrency($month['total_payments']) }}</span>
                        </div>
                        <div class="h-3 rounded-full overflow-hidden" style="background-color: rgba(var(--primary-rgb), 0.1);">
                            @php
                                $maxAmount = max(array_column($monthlyBreakdown, 'total_payments'));
                                $percentage = $maxAmount > 0 ? ($month['total_payments'] / $maxAmount) * 100 : 0;
                            @endphp
                            <div class="h-full rounded-full" 
                                 style="width: {{ $percentage }}%; background-color: var(--success);"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-8">
                    <i class="fas fa-chart-line text-3xl mb-4" style="color: var(--text-secondary); opacity: 0.5;"></i>
                    <p style="color: var(--text-secondary);">No payment data for this period</p>
                </div>
                @endif
            </div>
        </div>

        <!-- Payment Method Breakdown -->
        <div class="card">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-credit-card mr-2" style="color: var(--primary);"></i> Payment Method Analysis
                </h3>
            </div>
            <div class="p-6">
                @if(count($paymentMethodBreakdown) > 0)
                <div class="space-y-4">
                    @foreach($paymentMethodBreakdown as $method)
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span style="color: var(--text-primary);">{{ $method['payment_method'] }}</span>
                            <span style="color: var(--text-secondary);">
                                {{ formatCurrency($method['total_amount']) }} ({{ $method['count'] }})
                            </span>
                        </div>
                        <div class="h-3 rounded-full overflow-hidden" style="background-color: rgba(var(--primary-rgb), 0.1);">
                            @php
                                $totalAmount = $paymentMethodBreakdown->sum('total_amount');
                                $percentage = $totalAmount > 0 ? ($method['total_amount'] / $totalAmount) * 100 : 0;
                            @endphp
                            <div class="h-full rounded-full" 
                                 style="width: {{ $percentage }}%; background-color: var(--{{ $loop->index % 2 == 0 ? 'info' : 'primary' }});"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-8">
                    <i class="fas fa-credit-card text-3xl mb-4" style="color: var(--text-secondary); opacity: 0.5;"></i>
                    <p style="color: var(--text-secondary);">No payment method data for this period</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Agreements Table -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-table mr-2" style="color: var(--primary);"></i> Agreements in Period
                <span class="ml-2 text-sm font-normal" style="color: var(--text-secondary);">
                    ({{ $agreements->count() }} agreements)
                </span>
            </h3>
        </div>
        <div class="p-6">
            @if($agreements->count() > 0)
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Agreement</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Status</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Amount</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Paid</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Start Date</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($agreements as $agreement)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <div>
                                    <div class="font-mono text-sm font-medium" style="color: var(--text-primary);">{{ $agreement->agreement_number }}</div>
                                    <div class="text-xs truncate max-w-xs" style="color: var(--text-secondary);">
                                        {{ Str::limit($agreement->description, 40) }}
                                    </div>
                                </div>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                @php
                                    $statusClass = match($agreement->status) {
                                        'active' => 'badge-success',
                                        'pending' => 'badge-warning',
                                        'completed' => 'badge-info',
                                        'terminated' => 'badge-danger',
                                        default => 'badge-secondary'
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $statusClass }}">
                                    <i class="fas fa-circle mr-1" style="font-size: 0.5rem;"></i>
                                    {{ ucfirst($agreement->status) }}
                                </span>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <div class="font-medium" style="color: var(--text-primary);">{{ formatCurrency($agreement->amount, $agreement->currency) }}</div>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <div class="font-medium {{ $agreement->amount_received >= $agreement->amount ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400' }}">
                                    {{ formatCurrency($agreement->amount_received, $agreement->currency) }}
                                </div>
                                @if($agreement->amount > 0)
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    {{ round(($agreement->amount_received / $agreement->amount) * 100, 1) }}%
                                </div>
                                @endif
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <div style="color: var(--text-secondary);">{{ \Carbon\Carbon::parse($agreement->start_date)->format('M d, Y') }}</div>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <a href="{{ route('superadmin.billing.view-agreement', $agreement->id) }}" 
                                   class="px-2 py-1 rounded text-xs font-medium inline-flex items-center"
                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                    <i class="fas fa-eye text-xs"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-8">
                <i class="fas fa-inbox text-3xl mb-4" style="color: var(--text-secondary); opacity: 0.5;"></i>
                <p style="color: var(--text-secondary);">No agreements found in this period</p>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">Try adjusting the date range</p>
            </div>
            @endif
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-money-check-alt mr-2" style="color: var(--primary);"></i> Payments in Period
                <span class="ml-2 text-sm font-normal" style="color: var(--text-secondary);">
                    ({{ $payments->count() }} payments)
                </span>
            </h3>
        </div>
        <div class="p-6">
            @if($payments->count() > 0)
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Date</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Agreement</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Amount</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Method</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Status</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $payment)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <div style="color: var(--text-secondary);">{{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}</div>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <div class="font-mono text-sm font-medium" style="color: var(--text-primary);">
                                    {{ $payment->agreement_number ?? 'N/A' }}
                                </div>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <div class="font-medium" style="color: var(--text-primary);">
                                    {{ formatCurrency($payment->amount_paid, 'GHS') }}
                                </div>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-info">
                                    {{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}
                                </span>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                @php
                                    $statusClass = match($payment->status) {
                                        'confirmed' => 'badge-success',
                                        'pending_confirmation' => 'badge-warning',
                                        default => 'badge-secondary'
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $statusClass }}">
                                    <i class="fas fa-circle mr-1" style="font-size: 0.5rem;"></i>
                                    {{ ucfirst(str_replace('_', ' ', $payment->status)) }}
                                </span>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <a href="{{ route('superadmin.billing.payment-details', $payment->id) }}" 
                                   class="px-2 py-1 rounded text-xs font-medium inline-flex items-center"
                                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                                    <i class="fas fa-eye text-xs"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-8">
                <i class="fas fa-money-check-alt text-3xl mb-4" style="color: var(--text-secondary); opacity: 0.5;"></i>
                <p style="color: var(--text-secondary);">No payments found in this period</p>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">Try adjusting the date range</p>
            </div>
            @endif
            
            <!-- Payment Summary -->
            @if($payments->count() > 0)
            <div class="mt-6 pt-6 border-t" style="border-color: var(--border-color);">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--primary-rgb), 0.05);">
                        <div class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Payments</div>
                        <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ formatCurrency($stats['total_payment_amount']) }}</div>
                    </div>
                    <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--success-rgb), 0.05);">
                        <div class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Payment Count</div>
                        <div class="text-2xl font-bold" style="color: var(--success);">{{ $stats['total_payments'] }}</div>
                    </div>
                    <div class="text-center p-4 rounded-lg" style="background-color: rgba(var(--warning-rgb), 0.05);">
                        <div class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Pending Balance</div>
                        <div class="text-2xl font-bold" style="color: var(--warning);">{{ formatCurrency($stats['pending_payments']) }}</div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
@endsection

@section('scripts')
<script>
function exportReport() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'reports');
    showToast('Preparing report export...', 'info');
    
    // Add loading overlay
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

// Toast notification function
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

// Counter animation for stats
document.addEventListener('DOMContentLoaded', function() {
    const counters = document.querySelectorAll('.text-2xl.font-bold');
    counters.forEach(counter => {
        const text = counter.textContent;
        const isCurrency = text.includes('GH₵');
        const isNumber = /^[\d,]+$/.test(text.replace('GH₵', '').replace(/,/g, '').trim());
        
        if (isNumber || isCurrency) {
            let target;
            if (isCurrency) {
                target = parseFloat(text.replace('GH₵', '').replace(/,/g, '').trim());
            } else {
                target = parseInt(text.replace(/,/g, '').trim());
            }
            
            const duration = 1500;
            const increment = target / (duration / 16);
            let current = 0;
            
            const updateCounter = () => {
                current += increment;
                if (current >= target) {
                    counter.textContent = isCurrency ? 'GH₵' + target.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : target.toLocaleString();
                } else {
                    counter.textContent = isCurrency ? 'GH₵' + Math.floor(current).toLocaleString() : Math.floor(current).toLocaleString();
                    requestAnimationFrame(updateCounter);
                }
            };
            
            requestAnimationFrame(updateCounter);
        }
    });
});
</script>

<style>
/* Apply the same CSS styles as other blades */
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

/* Progress bar styling */
.h-3 div {
    transition: width 1s ease-out;
}

/* Animations */
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.fa-spinner.fa-spin {
    animation: spin 1s linear infinite;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
    
    .grid.grid-cols-1.lg\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
    
    .grid.grid-cols-1.md\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
    
    .table {
        font-size: 0.875rem;
    }
}

@media (max-width: 640px) {
    .p-6 {
        padding: 1rem !important;
    }
    
    .text-2xl {
        font-size: 1.5rem !important;
    }
    
    .table th,
    .table td {
        padding: 0.5rem !important;
    }
}

/* Scrollbar styling */
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

/* Loading overlay */
#exportOverlay {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Empty state styling */
.text-center i {
    opacity: 0.7;
}

.text-center h4 {
    margin-top: 1rem;
}

.text-center p {
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

/* Summary boxes */
.text-center.p-4.rounded-lg {
    transition: all 0.3s ease;
}

.text-center.p-4.rounded-lg:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
</style>
@endsection