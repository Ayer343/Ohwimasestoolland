@extends('layouts.landlord')

@section('title', 'Maintenance Analytics - Hilltop Estate')

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
    .badge-pending {
        background-color: rgba(var(--warning-rgb), 0.2);
        color: var(--warning);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .badge-in-progress {
        background-color: rgba(var(--info-rgb), 0.2);
        color: var(--info);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .badge-completed {
        background-color: rgba(var(--success-rgb), 0.2);
        color: var(--success);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .badge-cancelled {
        background-color: rgba(var(--danger-rgb), 0.2);
        color: var(--danger);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .badge-urgent {
        background-color: rgba(var(--danger-rgb), 0.2);
        color: var(--danger);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .badge-high {
        background-color: rgba(var(--warning-rgb), 0.2);
        color: var(--warning);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .badge-medium {
        background-color: rgba(var(--info-rgb), 0.2);
        color: var(--info);
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .badge-low {
        background-color: rgba(var(--success-rgb), 0.2);
        color: var(--success);
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
                <i class="fas fa-tools mr-2" style="color: var(--primary);"></i>
                Maintenance Analytics
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Track and analyze maintenance requests across your properties
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
                    <div class="stat-value" id="totalRequests">{{ $analytics['total_requests'] ?? 0 }}</div>
                    <div class="stat-label">Total Requests</div>
                </div>
                <div class="stat-icon stat-icon-primary">
                    <i class="fas fa-clipboard-list text-white text-xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <div class="stat-value" id="pendingRequests">{{ $analytics['pending_requests'] ?? 0 }}</div>
                    <div class="stat-label">Pending Requests</div>
                </div>
                <div class="stat-icon stat-icon-warning">
                    <i class="fas fa-clock text-white text-xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <div class="stat-value" id="urgentRequests">{{ $analytics['urgent_requests'] ?? 0 }}</div>
                    <div class="stat-label">Urgent Issues</div>
                </div>
                <div class="stat-icon stat-icon-danger">
                    <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="flex justify-between items-start">
                <div>
                    <div class="stat-value" id="avgResolutionTime">{{ $analytics['avg_resolution_time'] ?? 0 }} <span class="text-sm">days</span></div>
                    <div class="stat-label">Avg Resolution Time</div>
                </div>
                <div class="stat-icon stat-icon-info">
                    <i class="fas fa-hourglass-half text-white text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="chart-card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>Monthly Trend
                </h3>
                <div class="flex gap-2">
                    <button class="chart-period-btn active" data-period="6">Last 6 Months</button>
                    <button class="chart-period-btn" data-period="12">Last 12 Months</button>
                </div>
            </div>
            <canvas id="trendChart" height="250"></canvas>
        </div>

        <div class="chart-card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i>By Category
                </h3>
            </div>
            <canvas id="categoryChart" height="250"></canvas>
        </div>
    </div>

    <!-- Cost Analysis & By Property -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="chart-card p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i>Maintenance Cost Analysis
            </h3>
            <canvas id="costChart" height="250"></canvas>
        </div>

        <div class="chart-card p-6">
            <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-building mr-2" style="color: var(--primary);"></i>By Property
            </h3>
            <div class="overflow-x-auto max-h-80">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Property Name</th>
                            <th>Request Count</th>
                            <th>% of Total</th>
                        </tr>
                    </thead>
                    <tbody id="propertyTableBody">
                        @forelse(($analytics['by_property'] ?? []) as $property)
                        <tr>
                            <td>{{ $property->property_name ?? 'N/A' }}</td>
                            <td>{{ $property->count ?? 0 }}</td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <span>{{ $analytics['total_requests'] > 0 ? round(($property->count / $analytics['total_requests']) * 100) : 0 }}%</span>
                                    <div class="progress-bar-container flex-1">
                                        <div class="progress-bar-fill" style="width: {{ $analytics['total_requests'] > 0 ? round(($property->count / $analytics['total_requests']) * 100) : 0 }}%"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center py-4">No maintenance data available</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Maintenance Requests -->
    <div class="chart-card">
        <div class="px-6 py-4 border-b" style="border-color: var(--border-color);">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-list mr-2" style="color: var(--primary);"></i>Recent Maintenance Requests
                </h3>
                <a href="{{ route('landlord.maintenance.summary') }}" class="text-sm hover:underline" style="color: var(--primary);">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Property / Unit</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Reported</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="recentRequestsTable">
                    @forelse(($recentMaintenanceRequests ?? []) as $request)
                    <tr>
                        <td>
                            <div>
                                <div class="font-medium">{{ $request->unit->property->property_name ?? 'N/A' }}</div>
                                <div class="text-xs" style="color: var(--text-secondary);">Unit {{ $request->unit->unit_number ?? 'N/A' }}</div>
                            </div>
                        </td>
                        <td>{{ $request->title ?? 'N/A' }}</td>
                        <td>
                            <span class="badge-{{ strtolower($request->category ?? 'other') }}">{{ ucfirst($request->category ?? 'Other') }}</span>
                        </td>
                        <td>
                            <span class="badge-{{ strtolower($request->priority ?? 'medium') }}">{{ ucfirst($request->priority ?? 'Medium') }}</span>
                        </td>
                        <td>
                            <span class="badge-{{ str_replace('_', '-', $request->status ?? 'pending') }}">{{ ucfirst(str_replace('_', ' ', $request->status ?? 'Pending')) }}</span>
                        </td>
                        <td class="text-sm" style="color: var(--text-secondary);">{{ \Carbon\Carbon::parse($request->created_at)->diffForHumans() }}</td>
                        <td>
                            <a href="{{ route('property-units.maintenance-request-details', [$request->unit_id, $request->id]) }}" class="text-primary hover:underline text-sm">
                                View Details
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-8">
                            <i class="fas fa-tools text-3xl mb-2" style="color: var(--text-secondary); opacity: 0.5;"></i>
                            <p style="color: var(--text-secondary);">No maintenance requests found</p>
                        </td>
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
    maintenanceAnalytics: '{{ route("landlord.maintenance.analytics") }}',
    dashboardExport: '{{ route("landlord.dashboard.export") }}',
    csrfToken: '{{ csrf_token() }}'
};

// Store analytics data from server
const analyticsData = @json($analytics);
const monthlyTrend = @json($analytics['monthly_trend'] ?? []);
const byCategory = @json($analytics['by_category'] ?? []);
const costAnalysis = @json($analytics['cost_analysis'] ?? []);

let trendChart = null;
let categoryChart = null;
let costChart = null;

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
    
    // Period selector buttons
    $('.chart-period-btn[data-period]').on('click', function() {
        $('.chart-period-btn[data-period]').removeClass('active');
        $(this).addClass('active');
        loadTrendChart($(this).data('period'));
    });
}

function initializeCharts() {
    loadTrendChart(6);
    loadCategoryChart();
    loadCostChart();
}

function loadTrendChart(months) {
    let labels = [];
    let data = [];
    
    if (monthlyTrend && monthlyTrend.length > 0) {
        const trendData = monthlyTrend.slice(-months);
        labels = trendData.map(function(item) { return item.month; });
        data = trendData.map(function(item) { return item.count; });
    } else {
        // Generate sample data if none exists
        for (let i = months - 1; i >= 0; i--) {
            const date = new Date();
            date.setMonth(date.getMonth() - i);
            labels.push(date.toLocaleString('default', { month: 'short', year: 'numeric' }));
            data.push(0);
        }
    }
    
    const ctx = document.getElementById('trendChart').getContext('2d');
    if (trendChart) trendChart.destroy();
    
    trendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Maintenance Requests',
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
                            return context.raw + ' request(s)';
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

function loadCategoryChart() {
    let labels = [];
    let data = [];
    let backgroundColors = [];
    
    const colorMap = {
        'plumbing': 'rgba(59, 130, 246, 0.7)',
        'electrical': 'rgba(245, 158, 11, 0.7)',
        'appliance': 'rgba(16, 185, 129, 0.7)',
        'structural': 'rgba(139, 92, 246, 0.7)',
        'cleaning': 'rgba(6, 182, 212, 0.7)',
        'hvac': 'rgba(236, 72, 153, 0.7)',
        'other': 'rgba(107, 114, 128, 0.7)'
    };
    
    if (byCategory && byCategory.length > 0) {
        labels = byCategory.map(function(item) { 
            return (item.category || item.type || 'Other').charAt(0).toUpperCase() + (item.category || item.type || 'Other').slice(1); 
        });
        data = byCategory.map(function(item) { return item.count; });
        backgroundColors = labels.map(function(label) { 
            return colorMap[label.toLowerCase()] || 'rgba(107, 114, 128, 0.7)'; 
        });
    } else {
        labels = ['No Data'];
        data = [0];
        backgroundColors = ['rgba(107, 114, 128, 0.7)'];
    }
    
    const ctx = document.getElementById('categoryChart').getContext('2d');
    if (categoryChart) categoryChart.destroy();
    
    categoryChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: backgroundColors,
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
                            return label + ': ' + value + ' requests (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
}

function loadCostChart() {
    let labels = [];
    let data = [];
    
    if (costAnalysis && costAnalysis.length > 0) {
        labels = costAnalysis.map(function(item) { 
            return item.month + ' ' + item.year; 
        });
        data = costAnalysis.map(function(item) { 
            return parseFloat(item.total_cost || 0); 
        });
    } else {
        labels = ['No Data'];
        data = [0];
    }
    
    const ctx = document.getElementById('costChart').getContext('2d');
    if (costChart) costChart.destroy();
    
    costChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Maintenance Cost (GHS)',
                data: data,
                backgroundColor: 'rgba(59, 130, 246, 0.7)',
                borderColor: '#3b82f6',
                borderWidth: 1,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'GHS ' + context.raw.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'GHS ' + value.toLocaleString();
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
        url: routes.maintenanceAnalytics,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            updateStats(response);
            updateCharts(response);
            hideLoading();
            showToast('Maintenance data refreshed successfully!', 'success');
        },
        error: function(xhr) {
            hideLoading();
            console.error('Error refreshing data:', xhr);
            showToast('Error refreshing maintenance data. Please try again.', 'error');
        }
    });
}

function updateStats(data) {
    $('#totalRequests').text(data.total_requests || 0);
    $('#pendingRequests').text(data.pending_requests || 0);
    $('#urgentRequests').text(data.urgent_requests || 0);
    $('#avgResolutionTime').text((data.avg_resolution_time || 0) + ' days');
}

function updateCharts(data) {
    // Update trend chart
    if (data.monthly_trend && data.monthly_trend.length > 0) {
        const currentPeriod = $('.chart-period-btn.active').data('period') || 6;
        const trendData = data.monthly_trend.slice(-currentPeriod);
        const labels = trendData.map(function(item) { return item.month; });
        const counts = trendData.map(function(item) { return item.count; });
        
        if (trendChart) {
            trendChart.data.labels = labels;
            trendChart.data.datasets[0].data = counts;
            trendChart.update();
        }
    }
    
    // Update category chart
    if (data.by_category && data.by_category.length > 0) {
        const labels = data.by_category.map(function(item) { 
            return (item.category || 'Other').charAt(0).toUpperCase() + (item.category || 'Other').slice(1); 
        });
        const counts = data.by_category.map(function(item) { return item.count; });
        
        if (categoryChart) {
            categoryChart.data.labels = labels;
            categoryChart.data.datasets[0].data = counts;
            categoryChart.update();
        }
    }
    
    // Update cost chart
    if (data.cost_analysis && data.cost_analysis.length > 0) {
        const labels = data.cost_analysis.map(function(item) { 
            return item.month + ' ' + item.year; 
        });
        const costs = data.cost_analysis.map(function(item) { 
            return parseFloat(item.total_cost || 0); 
        });
        
        if (costChart) {
            costChart.data.labels = labels;
            costChart.data.datasets[0].data = costs;
            costChart.update();
        }
    }
    
    // Update property table
    if (data.by_property && data.by_property.length > 0 && data.total_requests) {
        let html = '';
        for (let i = 0; i < data.by_property.length; i++) {
            const property = data.by_property[i];
            const percentage = data.total_requests > 0 ? Math.round((property.count / data.total_requests) * 100) : 0;
            html += '<tr>' +
                '<td>' + escapeHtml(property.property_name || 'N/A') + '</td>' +
                '<td>' + (property.count || 0) + '</td>' +
                '<td>' +
                    '<div class="flex items-center gap-2">' +
                        '<span>' + percentage + '%</span>' +
                        '<div class="progress-bar-container flex-1">' +
                            '<div class="progress-bar-fill" style="width: ' + percentage + '%"></div>' +
                        '</div>' +
                    '</div>' +
                '</td>' +
            '</tr>';
        }
        $('#propertyTableBody').html(html);
    }
}

function exportReport() {
    showLoading();
    window.location.href = routes.dashboardExport + '?type=maintenance&format=csv';
    setTimeout(function() {
        hideLoading();
        showToast('Exporting maintenance report...', 'info');
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

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
@endpush