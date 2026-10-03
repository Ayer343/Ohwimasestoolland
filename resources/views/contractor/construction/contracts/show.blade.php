@extends('layouts.contract')

@section('title', $contract->title)

@section('content')
<div class="contract-detail-dashboard">
    <!-- Header -->
    <div class="card header-card">
        <div class="header-content">
            <div>
                <h2 class="page-title">
                    <i class="fas fa-file-contract mr-2"></i>
                    {{ $contract->title }}
                </h2>
                <p class="page-subtitle">
                    <i class="fas fa-file-signature mr-1"></i> 
                    Contract #{{ $contract->contract_number }} • Created {{ $contract->created_at->format('M d, Y') }}
                </p>
            </div>
            <div class="header-actions">
                @if(in_array($contract->status, ['approved', 'in_progress', 'on_hold']))
                    <a href="{{ route('contractor.contracts.progress', $contract) }}" 
                       class="btn btn-primary">
                        <i class="fas fa-chart-line mr-2"></i> 
                        <span>Update Progress</span>
                    </a>
                @endif
                <a href="{{ route('contractor.contracts.index') }}" 
                   class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> 
                    <span>Back to Contracts</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Status Banner -->
    <div class="status-banner status-{{ 
        $contract->status == 'pending_approval' ? 'warning' : 
        ($contract->status == 'approved' ? 'success' : 
        ($contract->status == 'in_progress' ? 'primary' : 
        ($contract->status == 'completed' ? 'success' : 
        ($contract->status == 'on_hold' ? 'warning' : 'secondary'))))
    }}">
        <div class="status-banner-content">
            <div class="status-banner-left">
                <span class="status-badge status-{{ 
                    $contract->status == 'pending_approval' ? 'warning' : 
                    ($contract->status == 'approved' ? 'success' : 
                    ($contract->status == 'in_progress' ? 'primary' : 
                    ($contract->status == 'completed' ? 'success' : 
                    ($contract->status == 'on_hold' ? 'warning' : 'secondary'))))
                }}">
                    <i class="fas fa-{{ 
                        $contract->status == 'pending_approval' ? 'clock' : 
                        ($contract->status == 'approved' ? 'check-circle' : 
                        ($contract->status == 'in_progress' ? 'hard-hat' : 
                        ($contract->status == 'completed' ? 'flag-checkered' : 
                        ($contract->status == 'on_hold' ? 'pause-circle' : 'file'))))
                    }} mr-2"></i>
                    Status: {{ ucfirst(str_replace('_', ' ', $contract->status)) }}
                </span>
                @if($contract->status == 'pending_approval')
                    <span class="status-helper">
                        <i class="fas fa-hourglass-half mr-1"></i> Awaiting admin approval
                    </span>
                @endif
            </div>
            <div class="status-banner-right">
                <i class="fas fa-calendar mr-1"></i>
                Last updated: {{ $contract->updated_at->diffForHumans() }}
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="detail-grid">
        <!-- Left Column -->
        <div class="detail-main">
            <!-- Contract Details -->
            <div class="card detail-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-file-contract mr-2" style="color: var(--primary);"></i> 
                        Contract Details
                    </h3>
                </div>
                <div class="card-body">
                    <div class="detail-grid-2col">
                        <div class="detail-item">
                            <label class="detail-label">Contract Title</label>
                            <p class="detail-value">{{ $contract->title }}</p>
                        </div>
                        <div class="detail-item">
                            <label class="detail-label">Property</label>
                            @if($contract->property)
                                <p class="detail-value">{{ $contract->property->property_name }}</p>
                                @if($contract->property->digital_address)
                                    <p class="detail-sub">
                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                        {{ $contract->property->digital_address }}
                                    </p>
                                @endif
                            @else
                                <p class="detail-value text-muted">N/A</p>
                            @endif
                        </div>
                        <div class="detail-item">
                            <label class="detail-label">Landlord</label>
                            <p class="detail-value">{{ $contract->landlord->name ?? 'N/A' }}</p>
                            @if($contract->landlord)
                                <p class="detail-sub">
                                    <i class="fas fa-envelope mr-1"></i>
                                    {{ $contract->landlord->email ?? 'N/A' }}
                                </p>
                            @endif
                        </div>
                        <div class="detail-item">
                            <label class="detail-label">Contract Amount</label>
                            <p class="detail-value amount">
                                @if($contract->contract_amount)
                                    {{ config('app.currency_symbol', '₵') }}{{ number_format($contract->contract_amount, 2) }}
                                @else
                                    <span class="text-muted">Not specified</span>
                                @endif
                            </p>
                        </div>
                        <div class="detail-item">
                            <label class="detail-label">Contractor Type</label>
                            <p class="detail-value">{{ ucfirst($contract->contractor_type) }}</p>
                        </div>
                        <div class="detail-item">
                            <label class="detail-label">Progress</label>
                            <div class="progress-container">
                                <span class="progress-text">
                                    {{ $progress ?? 0 }}%
                                </span>
                                <div class="progress-bar-track">
                                    <div class="progress-bar-fill" 
                                         style="width: {{ $progress ?? 0 }}%;"
                                         role="progressbar"
                                         aria-valuenow="{{ $progress ?? 0 }}"
                                         aria-valuemin="0"
                                         aria-valuemax="100">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    @if($contract->description)
                        <div class="detail-description">
                            <label class="detail-label">Description</label>
                            <p class="detail-value">{{ $contract->description }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Timeline -->
            <div class="card detail-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-clock mr-2" style="color: var(--primary);"></i> 
                        Timeline
                    </h3>
                </div>
                <div class="card-body">
                    <div class="timeline-grid">
                        <div class="timeline-item">
                            <label class="detail-label">Start Date</label>
                            <p class="detail-value">
                                {{ $contract->contract_start_date ? $contract->contract_start_date->format('M d, Y') : 'N/A' }}
                            </p>
                        </div>
                        <div class="timeline-item">
                            <label class="detail-label">Estimated Completion</label>
                            <p class="detail-value">
                                {{ $contract->estimated_completion_date ? $contract->estimated_completion_date->format('M d, Y') : 'N/A' }}
                            </p>
                            @if($contract->estimated_completion_date && $contract->estimated_completion_date->isPast() && !in_array($contract->status, ['completed', 'cancelled']))
                                <p class="detail-warning">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Overdue by {{ $contract->estimated_completion_date->diffInDays(now()) }} days
                                </p>
                            @endif
                        </div>
                        <div class="timeline-item">
                            <label class="detail-label">Duration</label>
                            @if($contract->contract_start_date && $contract->estimated_completion_date)
                                @php
                                    $days = $contract->contract_start_date->diffInDays($contract->estimated_completion_date);
                                @endphp
                                <p class="detail-value">
                                    {{ $days }} days ({{ round($days / 30, 1) }} months)
                                </p>
                            @else
                                <p class="detail-value text-muted">N/A</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Sidebar -->
        <div class="detail-sidebar">
            <!-- Milestones -->
            <div class="card detail-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-tasks mr-2" style="color: var(--primary);"></i> 
                        Milestones
                        <span class="milestone-count">
                            ({{ $milestoneStats['completed'] ?? 0 }}/{{ $milestoneStats['total'] ?? 0 }})
                        </span>
                    </h3>
                </div>
                <div class="card-body">
                    @if($milestoneStats['total'] > 0)
                        <div class="milestone-list">
                            @foreach($contract->milestones as $milestone)
                                <div class="milestone-item">
                                    <div class="milestone-status milestone-{{ $milestone->status }}">
                                        @if($milestone->status == 'completed')
                                            <i class="fas fa-check"></i>
                                        @elseif($milestone->status == 'in_progress')
                                            <i class="fas fa-spinner"></i>
                                        @elseif($milestone->status == 'delayed')
                                            <i class="fas fa-exclamation"></i>
                                        @else
                                            <i class="fas fa-circle"></i>
                                        @endif
                                    </div>
                                    <div class="milestone-info">
                                        <p class="milestone-title">{{ $milestone->title }}</p>
                                        <p class="milestone-date">
                                            Due: {{ $milestone->due_date->format('M d, Y') }}
                                        </p>
                                    </div>
                                    <div class="milestone-actions">
                                        <span class="milestone-badge milestone-badge-{{ $milestone->status }}">
                                            {{ ucfirst(str_replace('_', ' ', $milestone->status)) }}
                                        </span>
                                        <!-- ✅ NEW: Submit Milestone Button -->
                                        @if($milestone->status != 'completed' && in_array($contract->status, ['approved', 'in_progress', 'on_hold']))
                                            <a href="{{ route('contractor.milestones.submit.form', ['contract' => $contract, 'milestone' => $milestone]) }}" 
                                               class="btn-milestone-submit" 
                                               title="Submit Milestone for Review"
                                               onclick="return confirm('Are you sure you want to submit this milestone for review?')">
                                                <i class="fas fa-paper-plane"></i>
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        <!-- Milestone Stats -->
                        <div class="milestone-stats">
                            <div class="stat-box stat-box-success">
                                <div class="stat-box-value">{{ $milestoneStats['completed'] ?? 0 }}</div>
                                <div class="stat-box-label">Completed</div>
                            </div>
                            <div class="stat-box stat-box-warning">
                                <div class="stat-box-value">{{ $milestoneStats['in_progress'] ?? 0 }}</div>
                                <div class="stat-box-label">In Progress</div>
                            </div>
                            <div class="stat-box stat-box-danger">
                                <div class="stat-box-value">{{ $overdueMilestones ?? 0 }}</div>
                                <div class="stat-box-label">Overdue</div>
                            </div>
                        </div>
                    @else
                        <div class="empty-state-small">
                            <i class="fas fa-tasks"></i>
                            <p>No milestones defined yet</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card detail-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-cog mr-2" style="color: var(--primary);"></i> 
                        Quick Actions
                    </h3>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        @if(in_array($contract->status, ['approved', 'in_progress', 'on_hold']))
                            <a href="{{ route('contractor.contracts.progress', $contract) }}" 
                               class="btn btn-primary btn-block">
                                <i class="fas fa-chart-line mr-2"></i> Update Progress
                            </a>
                        @endif
                        
                        @if($contract->property)
                            <a href="{{ route('properties.show', $contract->property->id) }}" 
                               class="btn btn-outline btn-block">
                                <i class="fas fa-building mr-2"></i> View Property
                            </a>
                        @endif
                        
                        @if($contract->status == 'approved')
                            <div class="status-notice status-notice-success">
                                <i class="fas fa-check-circle"></i>
                                <p>Contract approved</p>
                                <span>Ready to start work</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
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
.contract-detail-dashboard {
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

.header-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
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

.btn-outline {
    background-color: transparent;
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.btn-outline:hover {
    background-color: var(--bg-secondary);
    transform: translateY(-1px);
}

.btn-block {
    width: 100%;
    justify-content: center;
}

/* ============================================ */
/* STATUS BANNER */
/* ============================================ */
.status-banner {
    padding: 1rem 1.5rem;
    border-radius: var(--radius);
    border-left: 4px solid var(--border-color);
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-left-width: 4px;
}

.status-banner-primary {
    border-left-color: var(--primary);
}

.status-banner-success {
    border-left-color: var(--success);
}

.status-banner-warning {
    border-left-color: var(--warning);
}

.status-banner-danger {
    border-left-color: var(--danger);
}

.status-banner-secondary {
    border-left-color: var(--secondary);
}

.status-banner-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.status-banner-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.status-banner-right {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.status-helper {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

/* ============================================ */
/* STATUS BADGE */
/* ============================================ */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.375rem 0.875rem;
    border-radius: 9999px;
    font-size: 0.813rem;
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
/* DETAIL GRID */
/* ============================================ */
.detail-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1.5rem;
}

.detail-main {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.detail-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.detail-card {
    margin-bottom: 0;
}

/* ============================================ */
/* DETAIL ITEMS */
/* ============================================ */
.detail-grid-2col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
}

.detail-label {
    font-size: 0.813rem;
    font-weight: 500;
    color: var(--text-secondary);
    margin-bottom: 0.25rem;
}

.detail-value {
    font-weight: 500;
    color: var(--text-primary);
    margin: 0;
}

.detail-value.amount {
    font-size: 1.25rem;
    font-weight: 700;
}

.detail-sub {
    font-size: 0.813rem;
    color: var(--text-secondary);
    margin-top: 0.125rem;
}

.detail-description {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
}

.detail-warning {
    font-size: 0.813rem;
    color: var(--danger);
    margin-top: 0.125rem;
}

.text-muted {
    color: var(--text-muted);
}

/* ============================================ */
/* PROGRESS BAR */
/* ============================================ */
.progress-container {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.progress-text {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--primary);
    min-width: 2.5rem;
}

.progress-bar-track {
    flex: 1;
    height: 0.5rem;
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

/* ============================================ */
/* TIMELINE */
/* ============================================ */
.timeline-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.25rem;
}

.timeline-item {
    display: flex;
    flex-direction: column;
}

/* ============================================ */
/* MILESTONES */
/* ============================================ */
.milestone-count {
    font-size: 0.875rem;
    font-weight: 400;
    color: var(--text-secondary);
    margin-left: 0.5rem;
}

.milestone-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    max-height: 350px;
    overflow-y: auto;
    padding-right: 0.25rem;
}

.milestone-list::-webkit-scrollbar {
    width: 4px;
}

.milestone-list::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 9999px;
}

.milestone-list::-webkit-scrollbar-thumb {
    background: var(--text-muted);
    border-radius: 9999px;
}

.milestone-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 0.75rem;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    transition: background-color 0.2s ease;
}

.milestone-item:hover {
    background-color: rgba(var(--primary-rgb), 0.02);
}

.milestone-status {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.75rem;
}

.milestone-completed {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
}

.milestone-in_progress {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
}

.milestone-delayed {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
}

.milestone-pending {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--secondary);
}

.milestone-info {
    flex: 1;
    min-width: 0;
}

.milestone-title {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-primary);
    margin: 0;
}

.milestone-date {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin: 0;
}

.milestone-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.milestone-badge {
    font-size: 0.688rem;
    padding: 0.125rem 0.5rem;
    border-radius: 9999px;
    font-weight: 500;
    white-space: nowrap;
    flex-shrink: 0;
}

.milestone-badge-completed {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
}

.milestone-badge-in_progress {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
}

.milestone-badge-delayed {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
}

.milestone-badge-pending {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--secondary);
}

/* ✅ NEW: Milestone Submit Button */
.btn-milestone-submit {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 50%;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
    text-decoration: none;
    font-size: 0.75rem;
}

.btn-milestone-submit:hover {
    background-color: var(--primary);
    color: #ffffff;
    transform: scale(1.1);
    box-shadow: 0 2px 8px rgba(var(--primary-rgb), 0.3);
}

/* ============================================ */
/* MILESTONE STATS */
/* ============================================ */
.milestone-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-color);
}

.stat-box {
    text-align: center;
    padding: 0.5rem;
    border-radius: 8px;
    background-color: var(--bg-secondary);
}

.stat-box-success .stat-box-value {
    color: var(--success);
}

.stat-box-warning .stat-box-value {
    color: var(--warning);
}

.stat-box-danger .stat-box-value {
    color: var(--danger);
}

.stat-box-value {
    font-size: 1.125rem;
    font-weight: 700;
}

.stat-box-label {
    font-size: 0.688rem;
    color: var(--text-secondary);
}

/* ============================================ */
/* EMPTY STATE (Small) */
/* ============================================ */
.empty-state-small {
    text-align: center;
    padding: 1.5rem 0;
    color: var(--text-secondary);
}

.empty-state-small i {
    font-size: 2rem;
    opacity: 0.3;
    display: block;
    margin-bottom: 0.5rem;
}

.empty-state-small p {
    font-size: 0.875rem;
    margin: 0;
}

/* ============================================ */
/* QUICK ACTIONS */
/* ============================================ */
.quick-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.status-notice {
    padding: 1rem;
    border-radius: 8px;
    text-align: center;
}

.status-notice-success {
    background: rgba(var(--success-rgb), 0.1);
}

.status-notice-success i {
    font-size: 1.25rem;
    color: var(--success);
    display: block;
    margin-bottom: 0.25rem;
}

.status-notice-success p {
    font-weight: 500;
    color: var(--text-primary);
    margin: 0;
}

.status-notice-success span {
    font-size: 0.813rem;
    color: var(--text-secondary);
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
@media (max-width: 1024px) {
    .detail-grid {
        grid-template-columns: 1fr;
    }
    
    .detail-sidebar {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
    }
}

@media (max-width: 768px) {
    .header-card .header-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .header-actions {
        width: 100%;
    }
    
    .header-actions .btn {
        flex: 1;
        justify-content: center;
    }
    
    .status-banner-content {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .status-banner-right {
        align-self: flex-start;
    }
    
    .detail-grid-2col {
        grid-template-columns: 1fr;
    }
    
    .timeline-grid {
        grid-template-columns: 1fr;
    }
    
    .detail-sidebar {
        grid-template-columns: 1fr;
    }
    
    .milestone-stats {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .milestone-actions {
        flex-direction: column;
        align-items: flex-end;
        gap: 0.25rem;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 480px) {
    .header-actions {
        flex-direction: column;
    }
    
    .header-actions .btn {
        width: 100%;
        justify-content: center;
    }
    
    .status-banner-left {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .milestone-item {
        flex-wrap: wrap;
    }
    
    .milestone-actions {
        flex-direction: row;
        align-items: center;
        margin-left: 2.5rem;
    }
    
    .milestone-stats {
        grid-template-columns: 1fr 1fr;
    }
    
    .milestone-stats .stat-box:last-child {
        grid-column: span 2;
    }
}
</style>
@endsection