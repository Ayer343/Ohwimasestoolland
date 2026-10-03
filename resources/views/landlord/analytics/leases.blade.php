@extends('layouts.landlord')

@section('title', 'Lease Analytics - Hilltop Estate')

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

    .analytics-container {
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
    .badge-active {
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

    .badge-expiring {
        background-color: rgba(var(--warning-rgb), 0.2);
        color: var(--warning);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .badge-expired {
        background-color: rgba(var(--danger-rgb), 0.2);
        color: var(--danger);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .badge-completed {
        background-color: rgba(var(--info-rgb), 0.2);
        color: var(--info);
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

    /* Chart Period Buttons */
    .chart-period-btn {
        padding: 0.25rem 0.75rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        cursor: pointer;
        background-color: var(--bg-secondary);
        color: var(--text-secondary);
        border: none;
    }

    .chart-period-btn.active {
        background-color: var(--primary);
        color: white;
    }

    .chart-period-btn:hover:not(.active) {
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
        .analytics-container {
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
<div class="analytics-container">
    <!-- Page Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                <i class="fas fa-file-signature mr-2" style="color: var(--primary);"></i>
                Lease Analytics
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Track and analyze lease agreements across your properties
            </p>
        </div>
        <div class="flex gap-2">
            <span class="text-sm" style="color: var(--text-secondary);">
                <i class="far fa-calendar-alt mr-1"></i>{{ now()->format('l, F j, Y') }}
            </span>
            <button class="chart-period-btn" id="refreshBtn">
                <i class="fas fa-sync-alt mr-1"></i> Refresh
            </button>
            <button class="chart-period-btn" id="exportBtn">
                <i class="fas fa-download mr-1"></i> Export Report
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <div class="stat-value" id="activeLeases">{{ $analytics['active_leases'] ?? 0 }}</div>
                    <div class="stat-label">Active Leases</div>
                </div>
                <div class="stat-icon stat-icon-success">
                    <i class="fas fa-check-circle text-white text-xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <div class="stat-value" id="expiringSoon">{{ $analytics['expiring_soon'] ?? 0 }}</div>
                    <div class="stat-label">Expiring Soon (30 days)</div>
                </div>
                <div class="stat-icon stat-icon-warning">
                    <i class="fas fa-hourglass-half text-white text-xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <div class="stat-value" id="pendingSignature">{{ $analytics['pending_signature'] ?? 0 }}</div>
                    <div class="stat-label">Pending Signature</div>
                </div>
                <div class="stat-icon stat-icon-info">
                    <i class="fas fa-signature text-white text-xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <div class="stat-value" id="renewalRate">{{ $analytics['renewal_rate'] ?? 0 }}%</div>
                    <div class="stat-label">Renewal Rate</div>
                </div>
                <div class="stat-icon stat-icon-primary">
                    <i class="fas fa-sync-alt text-white text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="chart-card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>Monthly Expirations
                </h3>
            </div>
            <canvas id="expirationsChart" height="250"></canvas>
        </div>

        <div class="chart-card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i>Lease Duration Distribution
                </h3>
            </div>
            <canvas id="durationChart" height="250"></canvas>
        </div>
    </div>

    <!-- Top Leases & Recent Leases -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="chart-card">
            <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-trophy mr-2" style="color: var(--primary);"></i>Top Income Leases
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Property / Unit</th>
                            <th>Tenant</th>
                            <th>Monthly Rent</th>
                            <th>Total Income</th>
                        </tr>
                    </thead>
                    <tbody id="topLeasesTable">
                        @forelse(($analytics['top_leases'] ?? []) as $lease)
                        <tr>
                            <td>
                                <div>
                                    <div class="font-medium">{{ $lease['property_name'] ?? 'N/A' }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">Unit {{ $lease['unit_number'] ?? 'N/A' }}</div>
                                </div>
                            </td>
                            <td>{{ $lease['tenant_name'] ?? 'N/A' }}</td>
                            <td class="font-medium" style="color: var(--primary);">GH₵{{ number_format($lease['monthly_rent'] ?? 0, 2) }}</td>
                            <td>GH₵{{ number_format($lease['total_income'] ?? 0, 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center py-4">No lease data available</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="chart-card">
            <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-clock mr-2" style="color: var(--primary);"></i>Recent Leases
                </h3>
            </div>
            <div class="overflow-x-auto max-h-96">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Property / Unit</th>
                            <th>Tenant</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="recentLeasesTable">
                        @forelse(($analytics['recent_leases'] ?? []) as $lease)
                        <tr>
                            <td>
                                <div>
                                    <div class="font-medium">{{ $lease['property_name'] ?? 'N/A' }}</div>
                                    <div class="text-xs" style="color: var(--text-secondary);">Unit {{ $lease['unit_number'] ?? 'N/A' }}</div>
                                </div>
                            </td>
                            <td>{{ $lease['tenant_name'] ?? 'N/A' }}</td>
                            <td>{{ isset($lease['start_date']) ? \Carbon\Carbon::parse($lease['start_date'])->format('M d, Y') : 'N/A' }}</td>
                            <td>{{ isset($lease['end_date']) ? \Carbon\Carbon::parse($lease['end_date'])->format('M d, Y') : 'N/A' }}</td>
                            <td>
                                @php
                                    $status = $lease['status'] ?? 'unknown';
                                    $badgeClass = match($status) {
                                        'active' => 'badge-active',
                                        'pending_signature' => 'badge-pending',
                                        'expiring' => 'badge-expiring',
                                        'expired' => 'badge-expired',
                                        'completed' => 'badge-completed',
                                        default => 'badge-pending'
                                    };
                                @endphp
                                <span class="{{ $badgeClass }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4">No recent leases found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Duration Distribution Details -->
    <div class="chart-card p-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i>Lease Duration Breakdown
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            @php
                $distribution = $analytics['duration_distribution'] ?? [];
                $totalActive = $analytics['active_leases'] ?? 1;
            @endphp
            <div class="text-center p-4" style="background-color: var(--bg-secondary); border-radius: 0.75rem;">
                <div class="text-2xl font-bold" style="color: var(--primary);">{{ $distribution['0-6_months'] ?? 0 }}</div>
                <div class="text-sm" style="color: var(--text-secondary);">0-6 Months</div>
                <div class="progress-bar-container mt-2">
                    <div class="progress-bar-fill" style="width: {{ $totalActive > 0 ? round((($distribution['0-6_months'] ?? 0) / $totalActive) * 100) : 0 }}%"></div>
                </div>
            </div>
            <div class="text-center p-4" style="background-color: var(--bg-secondary); border-radius: 0.75rem;">
                <div class="text-2xl font-bold" style="color: var(--primary);">{{ $distribution['6-12_months'] ?? 0 }}</div>
                <div class="text-sm" style="color: var(--text-secondary);">6-12 Months</div>
                <div class="progress-bar-container mt-2">
                    <div class="progress-bar-fill" style="width: {{ $totalActive > 0 ? round((($distribution['6-12_months'] ?? 0) / $totalActive) * 100) : 0 }}%"></div>
                </div>
            </div>
            <div class="text-center p-4" style="background-color: var(--bg-secondary); border-radius: 0.75rem;">
                <div class="text-2xl font-bold" style="color: var(--primary);">{{ $distribution['1-2_years'] ?? 0 }}</div>
                <div class="text-sm" style="color: var(--text-secondary);">1-2 Years</div>
                <div class="progress-bar-container mt-2">
                    <div class="progress-bar-fill" style="width: {{ $totalActive > 0 ? round((($distribution['1-2_years'] ?? 0) / $totalActive) * 100) : 0 }}%"></div>
                </div>
            </div>
            <div class="text-center p-4" style="background-color: var(--bg-secondary); border-radius: 0.75rem;">
                <div class="text-2xl font-bold" style="color: var(--primary);">{{ $distribution['2+_years'] ?? 0 }}</div>
                <div class="text-sm" style="color: var(--text-secondary);">2+ Years</div>
                <div class="progress-bar-container mt-2">
                    <div class="progress-bar-fill" style="width: {{ $totalActive > 0 ? round((($distribution['2+_years'] ?? 0) / $totalActive) * 100) : 0 }}%"></div>
                </div>
            </div>
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
    leasesAnalytics: '{{ route("landlord.leases.analytics") }}',
    dashboardExport: '{{ route("landlord.dashboard.export") }}',
    csrfToken: '{{ csrf_token() }}'
};

// Store analytics data from server
const analyticsData = @json($analytics);
const monthlyExpirations = @json($analytics['monthly_expirations'] ?? []);
const durationDistribution = @json($analytics['duration_distribution'] ?? []);

let expirationsChart = null;
let durationChart = null;

$(document).ready(function() {
    initializeCharts();
    setupEventHandlers();
});

function setupEventHandlers() {
    // Refresh button
    $('#refreshBtn').on('click', function() {
        refreshData();
    });
    
    // Export button
    $('#exportBtn').on('click', function() {
        exportReport();
    });
}

function initializeCharts() {
    loadExpirationsChart();
    loadDurationChart();
}

function loadExpirationsChart() {
    let labels = [];
    let data = [];
    
    if (monthlyExpirations && monthlyExpirations.length > 0) {
        labels = monthlyExpirations.map(function(item) { return item.month; });
        data = monthlyExpirations.map(function(item) { return item.count; });
    } else {
        // Generate sample data if none exists
        for (let i = 0; i <= 5; i++) {
            const date = new Date();
            date.setMonth(date.getMonth() + i);
            labels.push(date.toLocaleString('default', { month: 'short', year: 'numeric' }));
            data.push(0);
        }
    }
    
    const ctx = document.getElementById('expirationsChart').getContext('2d');
    if (expirationsChart) expirationsChart.destroy();
    
    expirationsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Leases Expiring',
                data: data,
                backgroundColor: 'rgba(245, 158, 11, 0.7)',
                borderColor: '#f59e0b',
                borderWidth: 1,
                borderRadius: 8
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
                            return context.raw + ' lease(s) expiring';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
}

function loadDurationChart() {
    let labels = [];
    let data = [];
    const backgroundColors = [
        'rgba(59, 130, 246, 0.7)',
        'rgba(16, 185, 129, 0.7)',
        'rgba(245, 158, 11, 0.7)',
        'rgba(139, 92, 246, 0.7)'
    ];
    
    if (durationDistribution && Object.keys(durationDistribution).length > 0) {
        labels = ['0-6 Months', '6-12 Months', '1-2 Years', '2+ Years'];
        data = [
            durationDistribution['0-6_months'] || 0,
            durationDistribution['6-12_months'] || 0,
            durationDistribution['1-2_years'] || 0,
            durationDistribution['2+_years'] || 0
        ];
    } else {
        labels = ['No Data'];
        data = [0];
    }
    
    const ctx = document.getElementById('durationChart').getContext('2d');
    if (durationChart) durationChart.destroy();
    
    durationChart = new Chart(ctx, {
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
                            return label + ': ' + value + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
}

function refreshData() {
    showLoading();
    
    $.ajax({
        url: routes.leasesAnalytics,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            updateStats(response);
            updateCharts(response);
            hideLoading();
            showToast('Lease data refreshed successfully!', 'success');
        },
        error: function(xhr) {
            hideLoading();
            console.error('Error refreshing data:', xhr);
            showToast('Error refreshing lease data. Please try again.', 'error');
        }
    });
}

function updateStats(data) {
    $('#activeLeases').text(data.active_leases || 0);
    $('#expiringSoon').text(data.expiring_soon || 0);
    $('#pendingSignature').text(data.pending_signature || 0);
    $('#renewalRate').text((data.renewal_rate || 0) + '%');
}

function updateCharts(data) {
    // Update expirations chart
    if (data.monthly_expirations && data.monthly_expirations.length > 0) {
        const labels = data.monthly_expirations.map(function(item) { return item.month; });
        const counts = data.monthly_expirations.map(function(item) { return item.count; });
        
        if (expirationsChart) {
            expirationsChart.data.labels = labels;
            expirationsChart.data.datasets[0].data = counts;
            expirationsChart.update();
        }
    }
    
    // Update duration chart
    if (data.duration_distribution && Object.keys(data.duration_distribution).length > 0) {
        const labels = ['0-6 Months', '6-12 Months', '1-2 Years', '2+ Years'];
        const counts = [
            data.duration_distribution['0-6_months'] || 0,
            data.duration_distribution['6-12_months'] || 0,
            data.duration_distribution['1-2_years'] || 0,
            data.duration_distribution['2+_years'] || 0
        ];
        
        if (durationChart) {
            durationChart.data.labels = labels;
            durationChart.data.datasets[0].data = counts;
            durationChart.update();
        }
    }
    
    // Update top leases table
    if (data.top_leases && data.top_leases.length > 0) {
        updateTopLeasesTable(data.top_leases);
    }
    
    // Update recent leases table
    if (data.recent_leases && data.recent_leases.length > 0) {
        updateRecentLeasesTable(data.recent_leases);
    }
    
    // Update duration breakdown boxes
    if (data.duration_distribution && data.active_leases) {
        updateDurationBreakdown(data.duration_distribution, data.active_leases);
    }
}

function updateTopLeasesTable(leases) {
    let html = '';
    for (let i = 0; i < leases.length; i++) {
        const lease = leases[i];
        html += '<tr>' +
            '<td><div><div class="font-medium">' + escapeHtml(lease.property_name || 'N/A') + '</div><div class="text-xs" style="color: var(--text-secondary);">Unit ' + escapeHtml(lease.unit_number || 'N/A') + '</div></div></td>' +
            '<td>' + escapeHtml(lease.tenant_name || 'N/A') + '</td>' +
            '<td class="font-medium" style="color: var(--primary);">GH₵' + formatNumber(lease.monthly_rent || 0) + '</td>' +
            '<td>GH₵' + formatNumber(lease.total_income || 0) + '</td>' +
        '</tr>';
    }
    $('#topLeasesTable').html(html);
}

function updateRecentLeasesTable(leases) {
    let html = '';
    for (let i = 0; i < leases.length; i++) {
        const lease = leases[i];
        const status = lease.status || 'unknown';
        let badgeClass = 'badge-pending';
        if (status === 'active') badgeClass = 'badge-active';
        else if (status === 'pending_signature') badgeClass = 'badge-pending';
        else if (status === 'expiring') badgeClass = 'badge-expiring';
        else if (status === 'expired') badgeClass = 'badge-expired';
        else if (status === 'completed') badgeClass = 'badge-completed';
        
        html += '<tr>' +
            '<td><div><div class="font-medium">' + escapeHtml(lease.property_name || 'N/A') + '</div><div class="text-xs" style="color: var(--text-secondary);">Unit ' + escapeHtml(lease.unit_number || 'N/A') + '</div></div></td>' +
            '<td>' + escapeHtml(lease.tenant_name || 'N/A') + '</td>' +
            '<td>' + (lease.start_date ? new Date(lease.start_date).toLocaleDateString() : 'N/A') + '</td>' +
            '<td>' + (lease.end_date ? new Date(lease.end_date).toLocaleDateString() : 'N/A') + '</td>' +
            '<td><span class="' + badgeClass + '">' + ucfirst(status.replace(/_/g, ' ')) + '</span></td>' +
        '</tr>';
    }
    $('#recentLeasesTable').html(html);
}

function updateDurationBreakdown(distribution, totalActive) {
    const total = totalActive || 1;
    const categories = [
        { id: '0-6_months', element: 0 },
        { id: '6-12_months', element: 1 },
        { id: '1-2_years', element: 2 },
        { id: '2+_years', element: 3 }
    ];
    
    const cards = document.querySelectorAll('.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-4 .text-center');
    categories.forEach(function(cat, index) {
        if (cards[index]) {
            const count = distribution[cat.id] || 0;
            const percentage = Math.round((count / total) * 100);
            const valueDiv = cards[index].querySelector('.text-2xl');
            const progressBar = cards[index].querySelector('.progress-bar-fill');
            if (valueDiv) valueDiv.textContent = count;
            if (progressBar) progressBar.style.width = percentage + '%';
        }
    });
}

function exportReport() {
    showLoading();
    window.location.href = routes.dashboardExport + '?type=leases&format=csv';
    setTimeout(function() {
        hideLoading();
        showToast('Exporting lease report...', 'info');
    }, 500);
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