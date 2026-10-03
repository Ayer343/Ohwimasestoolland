@extends('layouts.contract')

@section('title', 'My Contract')

@section('content')
<div class="contract-dashboard">
    <!-- Header -->
    <div class="card header-card">
        <div class="header-content">
            <div>
                <h2 class="page-title">
                    <i class="fas fa-file-contract mr-2"></i>
                    My Construction Contracts
                </h2>
                <p class="page-subtitle">
                    <i class="fas fa-hard-hat mr-1"></i> 
                    Manage all your construction contracts and projects
                </p>
            </div>
            <div class="current-date">
                <i class="fas fa-calendar-alt mr-1"></i>
                {{ now()->format('F j, Y') }}
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        @php
            $statsConfig = [
                'total' => ['icon' => 'fa-file-contract', 'color' => 'primary', 'label' => 'Total'],
                'active' => ['icon' => 'fa-play-circle', 'color' => 'success', 'label' => 'Active'],
                'in_progress' => ['icon' => 'fa-hard-hat', 'color' => 'primary', 'label' => 'In Progress'],
                'completed' => ['icon' => 'fa-flag-checkered', 'color' => 'success', 'label' => 'Completed'],
                'on_hold' => ['icon' => 'fa-pause-circle', 'color' => 'warning', 'label' => 'On Hold'],
                'overdue' => ['icon' => 'fa-exclamation-triangle', 'color' => 'danger', 'label' => 'Overdue'],
            ];
        @endphp
        
        @foreach($statsConfig as $key => $config)
            <div class="stat-card stat-card-{{ $config['color'] }}">
                <div class="stat-icon">
                    <i class="fas {{ $config['icon'] }}"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value">{{ $stats[$key] ?? 0 }}</div>
                    <div class="stat-label">{{ $config['label'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Filters -->
    <div class="card filter-card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-filter mr-2"></i> Filters
            </h3>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('contractor.contracts.index') }}" class="filter-form" role="search">
                @csrf
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="status" class="filter-label">Status</label>
                        <select name="status" id="status" class="form-control" aria-label="Filter by status">
                            <option value="">All Statuses</option>
                            @if(!empty($statuses) && is_array($statuses))
                                @foreach($statuses as $status)
                                    <option value="{{ e($status) }}" {{ request('status') == $status ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="search" class="filter-label">Search</label>
                        <input type="text" name="search" id="search" 
                               class="form-control"
                               placeholder="Contract #, Title, Property..."
                               value="{{ e(request('search')) }}"
                               aria-label="Search contracts">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_from" class="filter-label">Date From</label>
                        <input type="date" name="date_from" id="date_from" 
                               class="form-control"
                               value="{{ e(request('date_from')) }}"
                               aria-label="Date from">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_to" class="filter-label">Date To</label>
                        <input type="date" name="date_to" id="date_to" 
                               class="form-control"
                               value="{{ e(request('date_to')) }}"
                               aria-label="Date to">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                    <a href="{{ route('contractor.contracts.index') }}" 
                       class="btn btn-secondary">
                        <i class="fas fa-times mr-2"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Contracts Table -->
    <div class="card table-card">
        <div class="card-header">
            <div class="table-header">
                <h3 class="card-title">
                    <i class="fas fa-list mr-2"></i> Contracts
                </h3>
                @if(isset($contracts) && $contracts->count() > 0)
                    <div class="table-info" aria-live="polite">
                        Showing {{ $contracts->firstItem() }} to {{ $contracts->lastItem() }} 
                        of {{ $contracts->total() }} contracts
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
                                <th scope="col">Contract</th>
                                <th scope="col">Property</th>
                                <th scope="col">Amount</th>
                                <th scope="col">Progress</th>
                                <th scope="col">Status</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($contracts as $contract)
                                <tr>
                                    <td>
                                        <div class="contract-info">
                                            <div class="contract-number">
                                                <a href="{{ route('contractor.contracts.show', $contract->id) }}" 
                                                   class="contract-link">
                                                    {{ e($contract->contract_number) }}
                                                </a>
                                            </div>
                                            <div class="contract-title">
                                                {{ Str::limit(e($contract->title), 35) }}
                                            </div>
                                            <div class="contract-date">
                                                <i class="fas fa-calendar-alt mr-1"></i>
                                                {{ $contract->created_at->format('M d, Y') }}
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if($contract->property)
                                            <div class="property-info">
                                                <div class="property-name">
                                                    {{ Str::limit(e($contract->property->property_name), 25) }}
                                                </div>
                                                <div class="property-landlord">
                                                    <i class="fas fa-user mr-1"></i>
                                                    {{ e($contract->landlord->name ?? 'N/A') }}
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($contract->contract_amount)
                                            <div class="contract-amount">
                                                {{ config('app.currency_symbol', '₵') }}{{ number_format($contract->contract_amount, 2) }}
                                            </div>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
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
                                    <td>
                                        @php
                                            // Define status configurations
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
                                    <td>
                                        <div class="action-buttons">
                                            <a href="{{ route('contractor.contracts.show', $contract->id) }}" 
                                               class="action-btn action-view" 
                                               title="View Details"
                                               aria-label="View details for contract {{ e($contract->contract_number) }}">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @if(in_array($contract->status, ['approved', 'in_progress', 'on_hold']))
                                                <a href="{{ route('contractor.contracts.progress', $contract->id) }}" 
                                                   class="action-btn action-progress" 
                                                   title="Update Progress"
                                                   aria-label="Update progress for contract {{ e($contract->contract_number) }}">
                                                    <i class="fas fa-chart-line"></i>
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
                @if($contracts->hasPages())
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
                    <p class="empty-description">Try adjusting your filters or create a new contract</p>
                    @if(request()->hasAny(['status', 'search', 'date_from', 'date_to']))
                        <a href="{{ route('contractor.contracts.index') }}" class="btn btn-primary mt-4">
                            <i class="fas fa-times mr-2"></i> Clear All Filters
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide flash messages
    const flashMessages = document.querySelectorAll('.flash-message');
    flashMessages.forEach(function(message) {
        setTimeout(function() {
            message.classList.add('fade-out');
            setTimeout(function() {
                message.style.display = 'none';
            }, 500);
        }, 5000);
    });

    // Form validation for date range
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    
    if (dateFrom && dateTo) {
        dateFrom.addEventListener('change', function() {
            if (this.value && dateTo.value && this.value > dateTo.value) {
                dateTo.value = this.value;
            }
            if (this.value) {
                dateTo.min = this.value;
            }
        });
        
        dateTo.addEventListener('change', function() {
            if (this.value && dateFrom.value && this.value < dateFrom.value) {
                dateFrom.value = this.value;
            }
            if (this.value) {
                dateFrom.max = this.value;
            }
        });
    }

    // Confirm before destructive actions
    const deleteButtons = document.querySelectorAll('.action-delete');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
            }
        });
    });
});
</script>
@endpush

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

.page-title .fa-file-contract {
    color: var(--primary);
}

.page-subtitle {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

.current-date {
    font-size: 0.875rem;
    color: var(--text-secondary);
    padding: 0.5rem 1rem;
    background-color: var(--bg-secondary);
    border-radius: 6px;
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

/* Table Content Styles */
.contract-info {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.contract-number .contract-link {
    font-weight: 500;
    color: var(--primary);
    text-decoration: none;
}

.contract-number .contract-link:hover {
    text-decoration: underline;
}

.contract-title {
    font-size: 0.813rem;
    color: var(--text-primary);
}

.contract-date {
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

.property-landlord {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.contract-amount {
    font-weight: 700;
    color: var(--text-primary);
}

.text-muted {
    color: var(--text-muted);
}

/* Progress Bar */
.progress-container {
    display: flex;
    align-items: center;
    gap: 0.5rem;
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

.action-progress {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
}

.action-progress:hover {
    background-color: rgba(var(--success-rgb), 0.2);
}

.action-delete {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
}

.action-delete:hover {
    background-color: rgba(var(--danger-rgb), 0.2);
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
/* FLASH MESSAGES */
/* ============================================ */
.flash-message {
    transition: opacity 0.5s ease;
}

.flash-message.fade-out {
    opacity: 0;
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
    
    .current-date {
        align-self: flex-start;
    }
    
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .filter-grid {
        grid-template-columns: 1fr;
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
    .contracts-table th:nth-child(2),
    .contracts-table td:nth-child(2),
    .contracts-table th:nth-child(4),
    .contracts-table td:nth-child(4) {
        display: none;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filter-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .filter-actions .btn {
        width: 100%;
        justify-content: center;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 0.25rem;
    }
}
</style>
@endsection