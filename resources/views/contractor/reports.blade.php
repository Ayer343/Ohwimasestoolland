@extends('layouts.contract')

@section('title', 'Contractor Reports')

@section('content')
<div class="contract-dashboard">
    <!-- Header -->
    <div class="card header-card">
        <div class="header-content">
            <div>
                <h2 class="page-title">
                    <i class="fas fa-chart-bar mr-2"></i>
                    Reports
                </h2>
                <p class="page-subtitle">
                    <i class="fas fa-analytics mr-1"></i> 
                    View detailed reports and analytics for your contracts
                </p>
            </div>
            <div class="header-actions">
                <a href="{{ route('contractor.reports.export-csv', request()->all()) }}" class="btn btn-success">
                    <i class="fas fa-file-export mr-2"></i> Export CSV
                </a>
                <a href="{{ route('contractor.dashboard') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card filter-card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-filter mr-2"></i> Filters
            </h3>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('contractor.reports') }}" class="filter-form" role="search">
                @csrf
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="date_from" class="filter-label">Date From</label>
                        <input type="date" name="date_from" id="date_from" 
                               class="form-control"
                               value="{{ e($dateFrom ?? now()->subMonths(6)->format('Y-m-d')) }}"
                               aria-label="Date from">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_to" class="filter-label">Date To</label>
                        <input type="date" name="date_to" id="date_to" 
                               class="form-control"
                               value="{{ e($dateTo ?? now()->format('Y-m-d')) }}"
                               aria-label="Date to">
                    </div>
                    
                    <div class="filter-group">
                        <label for="status" class="filter-label">Status</label>
                        <select name="status" id="status" class="form-control" aria-label="Filter by status">
                            <option value="all" {{ ($status ?? 'all') === 'all' ? 'selected' : '' }}>All Statuses</option>
                            <option value="pending_approval" {{ ($status ?? '') === 'pending_approval' ? 'selected' : '' }}>Pending Approval</option>
                            <option value="approved" {{ ($status ?? '') === 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="in_progress" {{ ($status ?? '') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                            <option value="on_hold" {{ ($status ?? '') === 'on_hold' ? 'selected' : '' }}>On Hold</option>
                            <option value="completed" {{ ($status ?? '') === 'completed' ? 'selected' : '' }}>Completed</option>
                            <option value="cancelled" {{ ($status ?? '') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                    <a href="{{ route('contractor.reports') }}" 
                       class="btn btn-secondary">
                        <i class="fas fa-times mr-2"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="stats-grid">
        @php
            $summaryConfig = [
                'total_contracts' => ['icon' => 'fa-file-contract', 'color' => 'primary', 'label' => 'Total Contracts', 'value' => $summary['total_contracts'] ?? 0],
                'completed_contracts' => ['icon' => 'fa-check-double', 'color' => 'success', 'label' => 'Completed', 'value' => $summary['completed_contracts'] ?? 0],
                'active_contracts' => ['icon' => 'fa-play-circle', 'color' => 'primary', 'label' => 'Active', 'value' => $summary['active_contracts'] ?? 0],
                'total_value' => ['icon' => 'fa-dollar-sign', 'color' => 'warning', 'label' => 'Total Value', 'value' => '₵' . number_format($summary['total_value'] ?? 0, 0)],
            ];
        @endphp
        
        @foreach($summaryConfig as $key => $config)
            <div class="stat-card stat-card-{{ $config['color'] }}">
                <div class="stat-icon">
                    <i class="fas {{ $config['icon'] }}"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value">{{ $config['value'] }}</div>
                    <div class="stat-label">{{ $config['label'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Progress Chart -->
    <div class="card chart-card">
        <div class="card-header">
            <div class="table-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-line mr-2"></i> Contract Progress
                </h3>
            </div>
        </div>
        <div class="card-body">
            @if(!empty($progressData['labels']) && !empty($progressData['data']))
                <div class="chart-container">
                    <canvas id="progressChart" height="300"></canvas>
                </div>
            @else
                <!-- Empty State -->
                <div class="empty-state" role="status">
                    <div class="empty-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h4 class="empty-title">No data available</h4>
                    <p class="empty-description">No contract data available for the selected period</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Contract List -->
    <div class="card table-card">
        <div class="card-header">
            <div class="table-header">
                <h3 class="card-title">
                    <i class="fas fa-file-signature mr-2"></i> Contracts
                </h3>
                @if(isset($contracts) && $contracts->count() > 0)
                    <div class="table-info" aria-live="polite">
                        @if($contracts instanceof \Illuminate\Pagination\LengthAwarePaginator)
                            Showing {{ $contracts->firstItem() }} to {{ $contracts->lastItem() }} 
                            of {{ $contracts->total() }} contracts
                        @else
                            Showing {{ $contracts->count() }} contracts
                        @endif
                    </div>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if(isset($contracts) && $contracts->count() > 0)
                <div class="table-responsive">
                    <table class="contracts-table" role="table" aria-label="Contracts list">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Title</th>
                                <th scope="col">Property</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-right">Amount</th>
                                <th scope="col" class="text-center">Progress</th>
                                <th scope="col" class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($contracts as $index => $contract)
                                <tr>
                                    <td class="text-muted">{{ $loop->iteration }}</td>
                                    <td>
                                        <div class="contract-info">
                                            <div class="contract-title">
                                                {{ Str::limit(e($contract->title), 35) }}
                                            </div>
                                            <div class="contract-number">
                                                <span class="text-muted">{{ e($contract->contract_number) }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if($contract->property)
                                            <div class="property-info">
                                                <div class="property-name">
                                                    {{ Str::limit(e($contract->property->property_name), 25) }}
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $statusColors = [
                                                'pending_approval' => 'warning',
                                                'approved' => 'success',
                                                'in_progress' => 'primary',
                                                'completed' => 'success',
                                                'cancelled' => 'danger',
                                                'on_hold' => 'warning',
                                            ];
                                            $statusIcons = [
                                                'pending_approval' => 'fa-clock',
                                                'approved' => 'fa-check-circle',
                                                'in_progress' => 'fa-hard-hat',
                                                'completed' => 'fa-flag-checkered',
                                                'cancelled' => 'fa-times-circle',
                                                'on_hold' => 'fa-pause-circle',
                                            ];
                                            $statusLabels = [
                                                'pending_approval' => 'Pending Approval',
                                                'approved' => 'Approved',
                                                'in_progress' => 'In Progress',
                                                'completed' => 'Completed',
                                                'cancelled' => 'Cancelled',
                                                'on_hold' => 'On Hold',
                                            ];
                                            $statusColor = $statusColors[$contract->status] ?? 'secondary';
                                            $statusIcon = $statusIcons[$contract->status] ?? 'fa-question-circle';
                                            $statusLabel = $statusLabels[$contract->status] ?? ucfirst(str_replace('_', ' ', $contract->status));
                                        @endphp
                                        <span class="status-badge status-{{ $statusColor }}" role="status">
                                            <i class="fas {{ $statusIcon }} mr-1"></i>
                                            {{ $statusLabel }}
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <div class="contract-amount">
                                            ₵{{ number_format($contract->contract_amount, 2) }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="progress-container">
                                            <span class="progress-text">
                                                {{ $contract->progress_percentage ?? 0 }}%
                                            </span>
                                            <div class="progress-bar-track">
                                                <div class="progress-bar-fill" 
                                                     style="width: {{ $contract->progress_percentage ?? 0 }}%;"
                                                     role="progressbar"
                                                     aria-valuenow="{{ $contract->progress_percentage ?? 0 }}"
                                                     aria-valuemin="0"
                                                     aria-valuemax="100">
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-right">
                                        <div class="action-buttons">
                                            <a href="{{ route('contractor.contracts.show', $contract->id) }}" 
                                               class="action-btn action-view" 
                                               title="View Details"
                                               aria-label="View details for contract {{ e($contract->contract_number) }}">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($contracts instanceof \Illuminate\Pagination\LengthAwarePaginator && $contracts->hasPages())
                    <div class="pagination-container">
                        {{ $contracts->appends(request()->query())->links() }}
                    </div>
                @endif
            @else
                <!-- Empty State -->
                <div class="empty-state" role="status">
                    <div class="empty-icon">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <h4 class="empty-title">No contracts found</h4>
                    <p class="empty-description">No contracts found for the selected period and filters</p>
                    @if(request()->hasAny(['status', 'date_from', 'date_to']))
                        <a href="{{ route('contractor.reports') }}" class="btn btn-primary mt-4">
                            <i class="fas fa-times mr-2"></i> Clear All Filters
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

@push('styles')
<style>
/* ============================================ */
/* CSS VARIABLES (Should be in parent) */
/* ============================================ */
:root {
    --primary: #2563eb;
    --primary-rgb: 37, 99, 235;
    --secondary: #6b7280;
    --secondary-rgb: 107, 114, 128;
    --success: #16a34a;
    --success-rgb: 22, 163, 74;
    --warning: #f59e0b;
    --warning-rgb: 245, 158, 11;
    --danger: #dc2626;
    --danger-rgb: 220, 38, 38;
    --info: #06b6d4;
    --info-rgb: 6, 182, 212;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --text-muted: #9ca3af;
    --border-color: #e5e7eb;
    --card-bg: #ffffff;
    --bg-secondary: #f3f4f6;
    --bg-tertiary: #e5e7eb;
    --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --radius: 12px;
}

/* ============================================ */
/* BASE LAYOUT */
/* ============================================ */
.contract-dashboard {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

/* ============================================ */
/* CARD COMPONENT */
/* ============================================ */
.card {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    transition: all 0.2s ease;
}

.card:hover {
    box-shadow: var(--shadow-md);
}

.card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.card-body {
    padding: 1.5rem;
}

.card-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
}

/* ============================================ */
/* HEADER */
/* ============================================ */
.header-card .header-content {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
}

.page-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.page-title .fa-chart-bar {
    color: var(--primary);
}

.page-subtitle {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

.header-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

/* ============================================ */
/* STATISTICS GRID */
/* ============================================ */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 1rem;
}

.stat-card {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    padding: 1rem;
    display: flex;
    align-items: center;
    transition: all 0.2s ease;
    box-shadow: var(--shadow-sm);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.stat-icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
    flex-shrink: 0;
}

.stat-icon i {
    font-size: 1rem;
}

.stat-content {
    flex: 1;
    min-width: 0;
}

.stat-value {
    font-size: 1.25rem;
    font-weight: 700;
    line-height: 1.2;
}

.stat-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-top: 0.125rem;
}

/* Stat Colors */
.stat-card-primary .stat-icon {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
}
.stat-card-primary .stat-value {
    color: var(--primary);
}

.stat-card-success .stat-icon {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
}
.stat-card-success .stat-value {
    color: var(--success);
}

.stat-card-warning .stat-icon {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
}
.stat-card-warning .stat-value {
    color: var(--warning);
}

.stat-card-danger .stat-icon {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
}
.stat-card-danger .stat-value {
    color: var(--danger);
}

.stat-card-info .stat-icon {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
}
.stat-card-info .stat-value {
    color: var(--info);
}

/* ============================================ */
/* FILTERS */
/* ============================================ */
.filter-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-label {
    display: block;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
    font-size: 0.875rem;
}

.filter-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* ============================================ */
/* FORM CONTROLS */
/* ============================================ */
.form-control {
    width: 100%;
    padding: 0.625rem 0.875rem;
    font-size: 0.875rem;
    line-height: 1.5;
    color: var(--text-primary);
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    transition: all 0.2s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.form-control::placeholder {
    color: var(--text-muted);
}

/* ============================================ */
/* BUTTONS */
/* ============================================ */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.625rem 1.25rem;
    font-weight: 500;
    border-radius: 8px;
    border: 1px solid transparent;
    transition: all 0.2s ease;
    cursor: pointer;
    text-decoration: none;
    font-size: 0.875rem;
    gap: 0.25rem;
}

.btn-primary {
    background-color: var(--primary);
    color: #ffffff;
    border-color: var(--primary);
}

.btn-primary:hover {
    background-color: #1d4ed8;
    border-color: #1d4ed8;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.btn-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--secondary);
    border: 1px solid rgba(var(--secondary-rgb), 0.2);
}

.btn-secondary:hover {
    background-color: rgba(var(--secondary-rgb), 0.2);
    transform: translateY(-1px);
}

.btn-success {
    background-color: var(--success);
    color: #ffffff;
    border-color: var(--success);
}

.btn-success:hover {
    background-color: #15803d;
    border-color: #15803d;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.btn-outline {
    background-color: transparent;
    color: var(--primary);
    border-color: var(--primary);
}

.btn-outline:hover {
    background-color: rgba(var(--primary-rgb), 0.1);
    transform: translateY(-1px);
}

/* ============================================ */
/* TABLE */
/* ============================================ */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.contracts-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.contracts-table thead {
    border-bottom: 1px solid var(--border-color);
}

.contracts-table th {
    text-align: left;
    padding: 0.75rem 1rem;
    font-weight: 500;
    color: var(--text-secondary);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.contracts-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
}

.contracts-table tbody tr {
    transition: background-color 0.15s ease;
}

.contracts-table tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.02);
}

.text-right {
    text-align: right;
}

.text-center {
    text-align: center;
}

.text-muted {
    color: var(--text-muted);
}

/* Table Content Styles */
.contract-info {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.contract-title {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-primary);
}

.contract-number {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.property-info {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.property-name {
    font-weight: 500;
    color: var(--text-primary);
}

.contract-amount {
    font-weight: 700;
    color: var(--text-primary);
}

/* Progress Bar */
.progress-container {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    justify-content: center;
}

.progress-text {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--text-primary);
    min-width: 2.5rem;
}

.progress-bar-track {
    flex: 1;
    height: 0.375rem;
    min-width: 3rem;
    background-color: var(--bg-secondary);
    border-radius: 9999px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    border-radius: 9999px;
    background: linear-gradient(to right, var(--primary), var(--success));
    transition: width 0.6s ease;
}

/* Status Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    white-space: nowrap;
}

.status-primary {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
}

.status-success {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
}

.status-warning {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
}

.status-danger {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
}

.status-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--secondary);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.25rem;
    justify-content: flex-end;
    flex-wrap: wrap;
}

.action-btn {
    width: 2rem;
    height: 2rem;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    font-size: 0.875rem;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.action-view {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
}

.action-view:hover {
    background-color: rgba(var(--info-rgb), 0.2);
}

/* ============================================ */
/* TABLE INFO & HEADER */
/* ============================================ */
.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    width: 100%;
}

.table-info {
    font-size: 0.813rem;
    color: var(--text-secondary);
}

/* ============================================ */
/* CHART CONTAINER */
/* ============================================ */
.chart-container {
    position: relative;
    height: 300px;
    width: 100%;
}

.chart-container canvas {
    max-width: 100%;
    max-height: 100%;
}

/* ============================================ */
/* EMPTY STATE */
/* ============================================ */
.empty-state {
    padding: 3rem 1rem;
    text-align: center;
}

.empty-icon {
    font-size: 3rem;
    color: var(--text-muted);
    opacity: 0.5;
    margin-bottom: 1rem;
}

.empty-icon i {
    display: block;
}

.empty-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.empty-description {
    color: var(--text-secondary);
    margin-bottom: 0;
}

/* ============================================ */
/* PAGINATION */
/* ============================================ */
.pagination-container {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color);
}

.pagination-container nav {
    display: flex;
    justify-content: center;
}

/* ============================================ */
/* RESPONSIVE */
/* ============================================ */
@media (max-width: 768px) {
    .header-card .header-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .header-actions {
        width: 100%;
        flex-direction: column;
    }
    
    .header-actions .btn {
        width: 100%;
        justify-content: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .filter-actions .btn {
        width: 100%;
        justify-content: center;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .table-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .contracts-table {
        font-size: 0.813rem;
    }
    
    .contracts-table th,
    .contracts-table td {
        padding: 0.5rem;
    }
    
    .contracts-table td:last-child,
    .contracts-table th:last-child {
        padding-right: 0.5rem;
    }
    
    /* Hide less important columns on mobile */
    .contracts-table th:nth-child(1),
    .contracts-table td:nth-child(1),
    .contracts-table th:nth-child(3),
    .contracts-table td:nth-child(3) {
        display: none;
    }
    
    .chart-container {
        height: 200px;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }
    
    .stat-card {
        padding: 0.75rem;
    }
    
    .stat-icon {
        width: 2rem;
        height: 2rem;
    }
    
    .stat-value {
        font-size: 1rem;
    }
    
    .stat-label {
        font-size: 0.688rem;
    }
    
    .action-buttons {
        justify-content: center;
    }
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Progress Chart
    const progressCanvas = document.getElementById('progressChart');
    if (progressCanvas) {
        const ctx = progressCanvas.getContext('2d');
        
        const labels = @json($progressData['labels'] ?? []);
        const data = @json($progressData['data'] ?? []);
        
        if (labels.length > 0 && data.length > 0) {
            // Get theme colors
            const computedStyle = getComputedStyle(document.documentElement);
            const primaryColor = computedStyle.getPropertyValue('--primary').trim() || '#2563eb';
            const textSecondary = computedStyle.getPropertyValue('--text-secondary').trim() || '#6b7280';
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Progress (%)',
                        data: data,
                        backgroundColor: 'rgba(37, 99, 235, 0.5)',
                        borderColor: primaryColor,
                        borderWidth: 2,
                        borderRadius: 8,
                        barPercentage: 0.6,
                        categoryPercentage: 0.8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: { 
                                color: textSecondary,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Progress: ' + context.raw + '%';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            min: 0,
                            max: 100,
                            ticks: {
                                color: textSecondary,
                                callback: function(value) {
                                    return value + '%';
                                },
                                font: {
                                    size: 11
                                }
                            },
                            grid: { 
                                color: 'rgba(107, 114, 128, 0.1)',
                                drawBorder: false
                            }
                        },
                        x: {
                            ticks: {
                                color: textSecondary,
                                maxRotation: 45,
                                minRotation: 45,
                                font: {
                                    size: 11
                                }
                            },
                            grid: { 
                                display: false
                            }
                        }
                    }
                }
            });
        }
    }
});
</script>
@endpush
@endsection