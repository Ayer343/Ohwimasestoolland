@extends('layouts.landlord')

@section('title', 'Financial Summary - Hilltop Estate')

@push('styles')
<style>
    /* CSS Variables - Matching the admin dashboard theme */
    :root {
        --text-primary: #1f2937;
        --text-secondary: #6b7280;
        --card-bg: #ffffff;
        --bg-secondary: #f9fafb;
        --border-color: #e5e7eb;
        --primary: #3b82f6;
        --primary-rgb: 59, 130, 246;
        --secondary: #8b5cf6;
        --secondary-rgb: 139, 92, 246;
        --success: #10b981;
        --success-rgb: 16, 185, 129;
        --warning: #f59e0b;
        --warning-rgb: 245, 158, 11;
        --danger: #ef4444;
        --danger-rgb: 239, 68, 68;
        --info: #06b6d4;
        --info-rgb: 6, 182, 212;
        --border-color-rgb: 229, 231, 235;
    }

    .financial-container {
        max-width: 1600px;
        margin: 0 auto;
        padding: 1rem;
    }

    /* Stats Cards */
    .stat-card {
        background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%);
        border: 1px solid var(--border-color);
        border-radius: 0.75rem;
        padding: 1.5rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .stat-icon {
        width: 3rem;
        height: 3rem;
        border-radius: 9999px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .stat-icon-primary {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    }

    .stat-icon-success {
        background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
    }

    .stat-icon-warning {
        background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%);
    }

    .stat-icon-danger {
        background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
    }

    .stat-icon-info {
        background: linear-gradient(135deg, var(--info) 0%, #0891b2 100%);
    }

    .stat-value {
        font-size: 1.875rem;
        font-weight: bold;
        color: var(--text-primary);
    }

    .stat-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin-top: 0.25rem;
    }

    /* Chart Cards */
    .chart-card {
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 0.75rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .chart-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    /* Status Badges */
    .badge-paid {
        background-color: rgba(var(--success-rgb), 0.2);
        color: var(--success);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .badge-pending {
        background-color: rgba(var(--warning-rgb), 0.2);
        color: var(--warning);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .badge-overdue {
        background-color: rgba(var(--danger-rgb), 0.2);
        color: var(--danger);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    /* Table Styles */
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th {
        text-align: left;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-secondary);
        font-weight: 500;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .data-table td {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        font-size: 0.875rem;
    }

    .data-table tr:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }

    /* Progress Bar */
    .progress-bar-container {
        background-color: var(--bg-secondary);
        border-radius: 9999px;
        height: 0.5rem;
        overflow: hidden;
    }

    .progress-bar-fill {
        background-color: var(--primary);
        height: 100%;
        border-radius: 9999px;
        transition: width 0.3s ease;
    }

    /* Period Selector */
    .period-selector {
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        cursor: pointer;
        background-color: var(--bg-secondary);
        color: var(--text-secondary);
        border: none;
    }

    .period-selector.active {
        background-color: var(--primary);
        color: white;
    }

    .period-selector:hover:not(.active) {
        background-color: rgba(var(--primary-rgb), 0.1);
        color: var(--primary);
    }

    /* Toast Notification */
    .toast-notification {
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 8px;
        color: white;
        z-index: 9999;
        animation: slideIn 0.3s ease-out;
    }

    .toast-success {
        background-color: var(--success);
    }

    .toast-error {
        background-color: var(--danger);
    }

    .toast-info {
        background-color: var(--info);
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    /* Loading Spinner */
    .loading-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255,255,255,0.3);
        border-radius: 50%;
        border-top-color: white;
        animation: spin 0.6s linear infinite;
        margin-right: 8px;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Responsive */
    @media (max-width: 768px) {
        .financial-container {
            padding: 0.5rem;
        }
        
        .stat-value {
            font-size: 1.5rem;
        }
        
        .stat-card {
            padding: 1rem;
        }
        
        .stat-icon {
            width: 2.5rem;
            height: 2.5rem;
        }
        
        .stat-icon i {
            font-size: 1.25rem;
        }
    }
</style>
@endpush

@section('content')
<div class="financial-container">
    <!-- Page Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>
                Financial Summary
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Track your rental income, payments, and financial performance
            </p>
        </div>
        <div class="flex gap-2">
            <div class="flex gap-1">
                <button class="period-selector active" data-period="month">This Month</button>
                <button class="period-selector" data-period="quarter">This Quarter</button>
                <button class="period-selector" data-period="year">This Year</button>
            </div>
            <button class="period-selector" id="exportBtn">
                <i class="fas fa-download mr-1"></i> Export
            </button>
            <button class="period-selector" id="refreshBtn">
                <i class="fas fa-sync-alt mr-1"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <div class="stat-value" id="totalRevenue">GH₵{{ number_format($summary['total_revenue'] ?? 0, 2) }}</div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-icon stat-icon-success">
                    <i class="fas fa-arrow-up text-white text-xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <div class="stat-value" id="monthlyRevenue">GH₵{{ number_format($summary['monthly_revenue'] ?? 0, 2) }}</div>
                    <div class="stat-label">Monthly Revenue</div>
                </div>
                <div class="stat-icon stat-icon-primary">
                    <i class="fas fa-calendar-alt text-white text-xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <div class="stat-value" id="outstandingBalance">GH₵{{ number_format($summary['total_outstanding'] ?? 0, 2) }}</div>
                    <div class="stat-label">Outstanding Balance</div>
                </div>
                <div class="stat-icon stat-icon-warning">
                    <i class="fas fa-clock text-white text-xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <div class="stat-value" id="collectionRate">{{ $summary['collection_rate'] ?? 0 }}%</div>
                    <div class="stat-label">Collection Rate</div>
                </div>
                <div class="stat-icon stat-icon-info">
                    <i class="fas fa-percent text-white text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="chart-card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>Revenue Trend
                </h3>
            </div>
            <canvas id="revenueTrendChart" height="250"></canvas>
        </div>

        <div class="chart-card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i>Payment Methods
                </h3>
            </div>
            <canvas id="paymentMethodsChart" height="250"></canvas>
        </div>
    </div>

    <!-- Collection Rate & Invoice Status -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="chart-card p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-chart-simple mr-2" style="color: var(--primary);"></i>Collection Performance
            </h3>
            <div class="mb-4">
                <div class="flex justify-between mb-2">
                    <span class="text-sm" style="color: var(--text-secondary);">Overall Collection Rate</span>
                    <span class="text-sm font-semibold" id="collectionRateLabel">{{ $summary['collection_rate'] ?? 0 }}%</span>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" id="collectionProgressBar" style="width: {{ $summary['collection_rate'] ?? 0 }}%"></div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4 mt-4">
                <div class="text-center p-3" style="background-color: var(--bg-secondary); border-radius: 0.75rem;">
                    <div class="text-2xl font-bold" style="color: var(--success);" id="paidInvoicesCount">{{ $summary['paid_invoices'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Paid Invoices</div>
                </div>
                <div class="text-center p-3" style="background-color: var(--bg-secondary); border-radius: 0.75rem;">
                    <div class="text-2xl font-bold" style="color: var(--warning);" id="unpaidInvoicesCount">{{ ($summary['total_invoices'] ?? 0) - ($summary['paid_invoices'] ?? 0) }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Unpaid Invoices</div>
                </div>
                <div class="text-center p-3" style="background-color: var(--bg-secondary); border-radius: 0.75rem;">
                    <div class="text-2xl font-bold" style="color: var(--primary);" id="totalInvoicesCount">{{ $summary['total_invoices'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Total Invoices</div>
                </div>
                <div class="text-center p-3" style="background-color: var(--bg-secondary); border-radius: 0.75rem;">
                    <div class="text-2xl font-bold" style="color: var(--info);" id="successRate">{{ $summary['total_revenue'] ?? 0 > 0 ? round((($summary['paid_invoices'] ?? 0) / ($summary['total_invoices'] ?? 1)) * 100) : 0 }}%</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Success Rate</div>
                </div>
            </div>
        </div>

        <div class="chart-card p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-file-invoice mr-2" style="color: var(--primary);"></i>Recent Invoices
            </h3>
            <div class="overflow-x-auto max-h-80">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Property/Unit</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="recentInvoicesTable">
                        @forelse(($invoices ?? []) as $invoice)
                        <tr>
                            <td class="font-mono text-sm">{{ $invoice->invoice_number ?? 'N/A' }}</td>
                            <td>
                                <div>
                                    <div class="font-medium">{{ $invoice->unit->property->property_name ?? 'N/A' }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">Unit {{ $invoice->unit->unit_number ?? 'N/A' }}</div>
                                </div>
                            </td>
                            <td class="font-medium">GH₵{{ number_format($invoice->amount ?? 0, 2) }}</td>
                            <td class="text-sm">
                                {{ isset($invoice->due_date) ? \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') : 'N/A' }}
                                @if(isset($invoice->due_date) && \Carbon\Carbon::parse($invoice->due_date)->isPast() && $invoice->status !== 'paid')
                                    <span class="text-xs text-danger block">Overdue</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $status = $invoice->status ?? 'pending';
                                    $badgeClass = $status === 'paid' ? 'badge-paid' : ($status === 'overdue' ? 'badge-overdue' : 'badge-pending');
                                @endphp
                                <span class="{{ $badgeClass }}">{{ ucfirst($status) }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4">No invoices found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Payments -->
    <div class="chart-card">
        <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-credit-card mr-2" style="color: var(--primary);"></i>Recent Payments
                </h3>
                <a href="{{ route('landlord.payments.history') }}" class="text-sm hover:underline" style="color: var(--primary);">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Tenant</th>
                        <th>Property/Unit</th>
                        <th>Invoice #</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="recentPaymentsTable">
                    @forelse(($payments ?? []) as $payment)
                    <tr>
                        <td class="text-sm">{{ isset($payment->payment_date) ? \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') : 'N/A' }}</td>
                        <td class="font-medium">{{ $payment->invoices->first()->tenant->name ?? 'N/A' }}</td>
                        <td>
                            <div>
                                <div class="font-medium">{{ $payment->invoices->first()->unit->property->property_name ?? 'N/A' }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">Unit {{ $payment->invoices->first()->unit->unit_number ?? 'N/A' }}</div>
                            </div>
                        </td>
                        <td class="font-mono text-sm">{{ $payment->invoices->first()->invoice_number ?? 'N/A' }}</td>
                        <td class="font-medium" style="color: var(--success);">GH₵{{ number_format($payment->amount ?? 0, 2) }}</td>
                        <td>
                            <span class="text-sm">
                                @php
                                    $method = $payment->payment_method ?? 'other';
                                    $icon = match($method) {
                                        'cash' => 'fa-money-bill',
                                        'bank_transfer' => 'fa-university',
                                        'mobile_money' => 'fa-mobile-alt',
                                        'check' => 'fa-check',
                                        default => 'fa-credit-card'
                                    };
                                @endphp
                                <i class="fas {{ $icon }} mr-1"></i>
                                {{ ucfirst(str_replace('_', ' ', $method)) }}
                            </span>
                        </td>
                        <td><span class="badge-paid">Completed</span></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4">No payments found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Store routes
const routes = {
    financialExport: '{{ route("landlord.financial.export") }}',
    financialSummary: '{{ route("landlord.financial.summary") }}',
    csrfToken: '{{ csrf_token() }}'
};

// Store data from server
const summaryData = @json($summary);
const monthlyData = @json($financialData['monthly_breakdown'] ?? []);
const paymentMethods = @json($financialData['payment_methods'] ?? []);

let revenueTrendChart = null;
let paymentMethodsChart = null;

$(document).ready(function() {
    initializeCharts();
    setupEventHandlers();
});

function setupEventHandlers() {
    // Period selector buttons
    $('.period-selector[data-period]').on('click', function() {
        $('.period-selector[data-period]').removeClass('active');
        $(this).addClass('active');
        loadFinancialData($(this).data('period'));
    });
    
    // Refresh button
    $('#refreshBtn').on('click', function() {
        loadFinancialData($('.period-selector.active').data('period'));
    });
    
    // Export button
    $('#exportBtn').on('click', function() {
        exportReport();
    });
}

function initializeCharts() {
    loadRevenueTrendChart();
    loadPaymentMethodsChart();
}

function loadRevenueTrendChart() {
    let labels = [];
    let data = [];
    
    if (monthlyData && monthlyData.length > 0) {
        labels = monthlyData.map(function(item) { return item.month; });
        data = monthlyData.map(function(item) { return parseFloat(item.amount || 0); });
    } else {
        // Default data if none exists
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        labels = months;
        data = Array(12).fill(0);
    }
    
    const ctx = document.getElementById('revenueTrendChart').getContext('2d');
    if (revenueTrendChart) revenueTrendChart.destroy();
    
    revenueTrendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue (GH₵)',
                data: data,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#3b82f6',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'GH₵ ' + context.raw.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'GH₵ ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

function loadPaymentMethodsChart() {
    let labels = [];
    let data = [];
    const backgroundColors = [
        'rgba(59, 130, 246, 0.7)',
        'rgba(16, 185, 129, 0.7)',
        'rgba(245, 158, 11, 0.7)',
        'rgba(139, 92, 246, 0.7)',
        'rgba(239, 68, 68, 0.7)'
    ];
    
    if (paymentMethods && paymentMethods.length > 0) {
        labels = paymentMethods.map(function(item) {
            const method = item.payment_method || item.provider || 'other';
            return method.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
        });
        data = paymentMethods.map(function(item) { return parseFloat(item.total || 0); });
    } else {
        labels = ['No Data'];
        data = [0];
    }
    
    const ctx = document.getElementById('paymentMethodsChart').getContext('2d');
    if (paymentMethodsChart) paymentMethodsChart.destroy();
    
    paymentMethodsChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: backgroundColors.slice(0, labels.length),
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { position: 'right' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            return label + ': GH₵ ' + value.toLocaleString() + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
}

function loadFinancialData(period) {
    showLoading();
    
    $.ajax({
        url: routes.financialExport,
        type: 'GET',
        data: { period: period, format: 'json' },
        dataType: 'json',
        success: function(response) {
            updateUI(response);
            if (response.monthly_breakdown && response.monthly_breakdown.length > 0) {
                updateRevenueChart(response.monthly_breakdown);
            }
            if (response.payment_methods && response.payment_methods.length > 0) {
                updatePaymentMethodsChart(response.payment_methods);
            }
            hideLoading();
            showToast('Financial data updated for ' + period, 'success');
        },
        error: function(xhr) {
            hideLoading();
            console.error('Error loading financial data:', xhr);
            showToast('Error loading financial data. Please try again.', 'error');
        }
    });
}

function updateUI(data) {
    // Update stat cards
    $('#totalRevenue').text('GH₵' + formatNumber(data.total_revenue || 0));
    $('#monthlyRevenue').text('GH₵' + formatNumber(data.monthly_revenue || 0));
    $('#outstandingBalance').text('GH₵' + formatNumber(data.total_outstanding || 0));
    $('#collectionRate').text((data.collection_rate || 0) + '%');
    $('#collectionRateLabel').text((data.collection_rate || 0) + '%');
    $('#collectionProgressBar').css('width', (data.collection_rate || 0) + '%');
    
    // Update invoice stats
    const paidInvoices = data.paid_invoices || 0;
    const totalInvoices = data.total_invoices || 0;
    const unpaidInvoices = totalInvoices - paidInvoices;
    const successRate = totalInvoices > 0 ? Math.round((paidInvoices / totalInvoices) * 100) : 0;
    
    $('#paidInvoicesCount').text(paidInvoices);
    $('#unpaidInvoicesCount').text(unpaidInvoices);
    $('#totalInvoicesCount').text(totalInvoices);
    $('#successRate').text(successRate + '%');
    
    // Update tables
    if (data.recent_invoices && data.recent_invoices.length > 0) {
        updateInvoicesTable(data.recent_invoices);
    }
    if (data.recent_payments && data.recent_payments.length > 0) {
        updatePaymentsTable(data.recent_payments);
    }
}

function updateRevenueChart(monthlyBreakdown) {
    const labels = monthlyBreakdown.map(function(item) { return item.month; });
    const data = monthlyBreakdown.map(function(item) { return parseFloat(item.amount || 0); });
    
    if (revenueTrendChart) {
        revenueTrendChart.data.labels = labels;
        revenueTrendChart.data.datasets[0].data = data;
        revenueTrendChart.update();
    }
}

function updatePaymentMethodsChart(methodsData) {
    const labels = methodsData.map(function(item) {
        const method = item.payment_method || item.provider || 'other';
        return method.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
    });
    const data = methodsData.map(function(item) { return parseFloat(item.total || 0); });
    
    if (paymentMethodsChart) {
        paymentMethodsChart.data.labels = labels;
        paymentMethodsChart.data.datasets[0].data = data;
        paymentMethodsChart.update();
    }
}

function updateInvoicesTable(invoices) {
    let html = '';
    for (let i = 0; i < invoices.length; i++) {
        const invoice = invoices[i];
        const isOverdue = invoice.due_date && new Date(invoice.due_date) < new Date() && invoice.status !== 'paid';
        const badgeClass = invoice.status === 'paid' ? 'badge-paid' : (isOverdue ? 'badge-overdue' : 'badge-pending');
        
        html += '<tr>' +
            '<td class="font-mono text-sm">' + escapeHtml(invoice.invoice_number || 'N/A') + '</td>' +
            '<td>' +
                '<div>' +
                    '<div class="font-medium">' + escapeHtml(invoice.property_name || 'N/A') + '</div>' +
                    '<div class="text-xs" style="color: var(--text-secondary);">Unit ' + escapeHtml(invoice.unit_number || 'N/A') + '</div>' +
                '</div>' +
            '</td>' +
            '<td class="font-medium">GH₵' + formatNumber(invoice.amount || 0) + '</td>' +
            '<td class="text-sm">' +
                (invoice.due_date ? new Date(invoice.due_date).toLocaleDateString() : 'N/A') +
                (isOverdue ? '<span class="text-xs text-danger block">Overdue</span>' : '') +
            '</td>' +
            '<td><span class="' + badgeClass + '">' + (invoice.status || 'pending') + '</span></td>' +
        '</tr>';
    }
    $('#recentInvoicesTable').html(html);
}

function updatePaymentsTable(payments) {
    let html = '';
    for (let i = 0; i < payments.length; i++) {
        const payment = payments[i];
        const method = payment.payment_method || 'other';
        let icon = 'fa-credit-card';
        if (method === 'cash') icon = 'fa-money-bill';
        else if (method === 'bank_transfer') icon = 'fa-university';
        else if (method === 'mobile_money') icon = 'fa-mobile-alt';
        else if (method === 'check') icon = 'fa-check';
        
        html += '<tr>' +
            '<td class="text-sm">' + (payment.payment_date ? new Date(payment.payment_date).toLocaleDateString() : 'N/A') + '</td>' +
            '<td class="font-medium">' + escapeHtml(payment.tenant_name || 'N/A') + '</td>' +
            '<td>' +
                '<div>' +
                    '<div class="font-medium">' + escapeHtml(payment.property_name || 'N/A') + '</div>' +
                    '<div class="text-xs" style="color: var(--text-secondary);">Unit ' + escapeHtml(payment.unit_number || 'N/A') + '</div>' +
                '</div>' +
            '</td>' +
            '<td class="font-mono text-sm">' + escapeHtml(payment.invoice_number || 'N/A') + '</td>' +
            '<td class="font-medium" style="color: var(--success);">GH₵' + formatNumber(payment.amount || 0) + '</td>' +
            '<td><i class="fas ' + icon + ' mr-1"></i>' + ucfirst(method.replace(/_/g, ' ')) + '</td>' +
            '<td><span class="badge-paid">Completed</span></td>' +
        '</tr>';
    }
    $('#recentPaymentsTable').html(html);
}

function exportReport() {
    const period = $('.period-selector.active').data('period');
    window.location.href = routes.financialExport + '?period=' + period + '&format=csv';
    showToast('Exporting financial report...', 'info');
}

function showLoading() {
    $('#refreshBtn').html('<span class="loading-spinner"></span> Loading...');
    $('#refreshBtn').prop('disabled', true);
}

function hideLoading() {
    $('#refreshBtn').html('<i class="fas fa-sync-alt mr-1"></i> Refresh');
    $('#refreshBtn').prop('disabled', false);
}

function showToast(message, type) {
    type = type || 'success';
    const toast = $('<div class="toast-notification toast-' + type + '">' + message + '</div>');
    $('body').append(toast);
    setTimeout(function() {
        toast.fadeOut(300, function() { toast.remove(); });
    }, 3000);
}

function formatNumber(num) {
    if (!num && num !== 0) return '0';
    return parseFloat(num).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function ucfirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}
</script>
@endpush