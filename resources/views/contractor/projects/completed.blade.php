@extends('layouts.contract')

@section('title', 'Completed Projects')

@section('content')
<div class="contract-dashboard">
    <!-- Header -->
    <div class="card header-card">
        <div class="header-content">
            <div>
                <h2 class="page-title">
                    <i class="fas fa-check-double mr-2"></i>
                    Completed Projects
                </h2>
                <p class="page-subtitle">
                    <i class="fas fa-trophy mr-1"></i> 
                    View all your completed construction projects
                </p>
            </div>
            <div class="header-actions">
                <a href="{{ route('contractor.contracts.index', ['status' => 'completed']) }}" 
                   class="btn btn-outline">
                    <i class="fas fa-list mr-2"></i> View All Contracts
                </a>
                <a href="{{ route('contractor.dashboard') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        @php
            $statsConfig = [
                'total' => ['icon' => 'fa-check-double', 'color' => 'success', 'label' => 'Total Completed', 'value' => $stats['total'] ?? 0],
                'this_month' => ['icon' => 'fa-calendar-check', 'color' => 'primary', 'label' => 'This Month', 'value' => $stats['this_month'] ?? 0],
                'this_year' => ['icon' => 'fa-calendar-alt', 'color' => 'info', 'label' => 'This Year', 'value' => $stats['this_year'] ?? 0],
                'avg_days' => ['icon' => 'fa-clock', 'color' => 'warning', 'label' => 'Avg. Completion Time', 'value' => number_format($stats['avg_completion_days'] ?? 0, 1) . ' days'],
            ];
        @endphp
        
        @foreach($statsConfig as $key => $config)
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

    <!-- Projects List -->
    <div class="card table-card">
        <div class="card-header">
            <div class="table-header">
                <h3 class="card-title">
                    <i class="fas fa-check-circle mr-2"></i> Completed Projects
                </h3>
                @if(isset($contracts) && $contracts->count() > 0)
                    <div class="table-info" aria-live="polite">
                        Showing {{ $contracts->firstItem() }} to {{ $contracts->lastItem() }} 
                        of {{ $contracts->total() }} completed projects
                    </div>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if(isset($contracts) && $contracts->count() > 0)
                <div class="projects-list">
                    @foreach($contracts as $contract)
                        <div class="project-card project-card-completed">
                            <div class="project-header">
                                <div class="project-info">
                                    <div class="project-title-group">
                                        <h4 class="project-title">
                                            <a href="{{ route('contractor.contracts.show', $contract) }}" 
                                               class="project-link">
                                                {{ e($contract->title) }}
                                            </a>
                                        </h4>
                                        <span class="status-badge status-success" role="status">
                                            <i class="fas fa-check mr-1"></i>
                                            Completed
                                        </span>
                                    </div>
                                    <div class="project-meta">
                                        <span class="meta-item">
                                            <i class="fas fa-building mr-1"></i>
                                            {{ e($contract->property->property_name ?? 'N/A') }}
                                        </span>
                                        <span class="meta-separator">•</span>
                                        <span class="meta-item">
                                            <i class="fas fa-user mr-1"></i>
                                            {{ e($contract->landlord->name ?? 'N/A') }}
                                        </span>
                                        <span class="meta-separator">•</span>
                                        <span class="meta-item">
                                            <i class="fas fa-calendar-alt mr-1"></i>
                                            Completed: {{ $contract->completed_at ? $contract->completed_at->format('M d, Y') : 'N/A' }}
                                        </span>
                                    </div>
                                    <div class="project-meta-extras">
                                        <span class="meta-item">
                                            <i class="fas fa-clock mr-1"></i>
                                            Duration: 
                                            @if($contract->contract_start_date && $contract->completed_at)
                                                {{ $contract->contract_start_date->diffInDays($contract->completed_at) }} days
                                            @else
                                                N/A
                                            @endif
                                        </span>
                                        <span class="meta-separator">•</span>
                                        <span class="meta-item">
                                            <i class="fas fa-trophy mr-1" style="color: var(--warning);"></i>
                                            @if($contract->contract_start_date && $contract->completed_at && $contract->estimated_completion_date)
                                                @if($contract->completed_at <= $contract->estimated_completion_date)
                                                    <span class="text-success">On Time</span>
                                                @else
                                                    <span class="text-danger">Delayed by {{ $contract->estimated_completion_date->diffInDays($contract->completed_at) }} days</span>
                                                @endif
                                            @else
                                                N/A
                                            @endif
                                        </span>
                                    </div>
                                </div>
                                <div class="project-amount">
                                    <div class="amount-value">
                                        {{ config('app.currency_symbol', '₵') }}{{ number_format($contract->contract_amount, 2) }}
                                    </div>
                                </div>
                            </div>

                            @if($contract->milestones && $contract->milestones->count() > 0)
                                <div class="project-milestones">
                                    @php
                                        $total = $contract->milestones->count();
                                        $completed = $contract->milestones->where('status', 'completed')->count();
                                        $completionRate = $total > 0 ? round(($completed / $total) * 100) : 0;
                                    @endphp
                                    <div class="milestones-info">
                                        <i class="fas fa-flag-checkered mr-2"></i>
                                        <span>Milestones:</span>
                                        <span class="milestone-completed">{{ $completed }} completed</span>
                                        <span class="milestone-total">({{ $completionRate }}% completion rate)</span>
                                    </div>
                                </div>
                            @endif

                            <div class="project-actions">
                                <a href="{{ route('contractor.contracts.show', $contract) }}" 
                                   class="action-btn action-view" 
                                   title="View Details"
                                   aria-label="View details for completed project {{ e($contract->title) }}">
                                    <i class="fas fa-eye mr-1"></i> View Details
                                </a>
                                @if($contract->can_generate_certificate ?? false)
                                    <a href="{{ route('contractor.contracts.certificate', $contract) }}" 
                                       class="action-btn action-certificate" 
                                       title="Generate Certificate"
                                       aria-label="Generate completion certificate for {{ e($contract->title) }}">
                                        <i class="fas fa-certificate mr-1"></i> Certificate
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
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
                        <i class="fas fa-check-double"></i>
                    </div>
                    <h4 class="empty-title">No completed projects yet</h4>
                    <p class="empty-description">Your completed projects will appear here once you finish them.</p>
                    <a href="{{ route('contractor.contracts.index') }}" class="btn btn-primary mt-4">
                        <i class="fas fa-file-signature mr-2"></i> View All Contracts
                    </a>
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

.page-title .fa-check-double {
    color: var(--success);
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

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
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
/* PROJECT CARDS */
/* ============================================ */
.projects-list {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}

.project-card {
    background-color: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    padding: 1.25rem;
    transition: all 0.2s ease;
}

.project-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-sm);
}

.project-card-completed {
    border-left: 4px solid var(--success);
}

.project-card-completed:hover {
    border-left-color: var(--success);
    border-color: var(--success);
}

.project-header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 0.75rem;
}

.project-info {
    flex: 1;
    min-width: 200px;
}

.project-title-group {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.25rem;
}

.project-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.project-link {
    color: var(--text-primary);
    text-decoration: none;
    transition: color 0.2s ease;
}

.project-link:hover {
    color: var(--primary);
    text-decoration: underline;
}

.project-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.813rem;
    color: var(--text-secondary);
}

.project-meta-extras {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.813rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

.meta-item {
    display: inline-flex;
    align-items: center;
}

.meta-separator {
    color: var(--text-muted);
    padding: 0 0.25rem;
}

.text-success {
    color: var(--success);
}

.text-danger {
    color: var(--danger);
}

.project-amount {
    flex-shrink: 0;
}

.amount-value {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text-primary);
}

/* ============================================ */
/* STATUS BADGES */
/* ============================================ */
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

/* ============================================ */
/* MILESTONES */
/* ============================================ */
.project-milestones {
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--border-color);
}

.milestones-info {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.813rem;
    color: var(--text-secondary);
}

.milestones-info i {
    color: var(--success);
}

.milestone-completed {
    color: var(--success);
}

.milestone-progress {
    color: var(--primary);
}

.milestone-pending {
    color: var(--text-muted);
}

.milestone-total {
    color: var(--text-secondary);
}

/* ============================================ */
/* PROJECT ACTIONS */
/* ============================================ */
.project-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--border-color);
}

.action-btn {
    display: inline-flex;
    align-items: center;
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.813rem;
    font-weight: 500;
    text-decoration: none;
    border: 1px solid transparent;
    transition: all 0.2s ease;
    cursor: pointer;
}

.action-btn:hover {
    transform: translateY(-1px);
}

.action-view {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
    border-color: rgba(var(--info-rgb), 0.2);
}

.action-view:hover {
    background-color: rgba(var(--info-rgb), 0.2);
}

.action-certificate {
    background-color: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
    border-color: rgba(var(--warning-rgb), 0.2);
}

.action-certificate:hover {
    background-color: rgba(var(--warning-rgb), 0.2);
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
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .table-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .project-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .project-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
    
    .project-meta-extras {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }
    
    .meta-separator {
        display: none;
    }
    
    .project-actions {
        flex-direction: column;
    }
    
    .project-actions .action-btn {
        width: 100%;
        justify-content: center;
    }
    
    .milestones-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
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
    
    .project-card {
        padding: 1rem;
    }
    
    .project-title {
        font-size: 0.875rem;
    }
    
    .project-title-group {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .amount-value {
        font-size: 1rem;
    }
}
</style>
@endpush
@endsection