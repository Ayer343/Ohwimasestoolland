@extends('layouts.contract')

@section('title', 'Contractor Calendar')

@section('content')
<div class="contract-dashboard">
    <!-- Header -->
    <div class="card header-card">
        <div class="header-content">
            <div>
                <h2 class="page-title">
                    <i class="fas fa-calendar-alt mr-2"></i>
                    Project Calendar
                </h2>
                <p class="page-subtitle">
                    <i class="fas fa-clock mr-1"></i> 
                    View all your project milestones and deadlines
                </p>
            </div>
            <div class="header-actions">
                <a href="{{ route('contractor.dashboard') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Upcoming Events -->
    <div class="card table-card">
        <div class="card-header">
            <div class="table-header">
                <h3 class="card-title">
                    <i class="fas fa-clock mr-2"></i> Upcoming Events (Next 30 Days)
                </h3>
                @if(isset($upcomingEvents) && $upcomingEvents->isNotEmpty())
                    <div class="table-info" aria-live="polite">
                        {{ $upcomingEvents->count() }} upcoming events
                    </div>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if(isset($upcomingEvents) && $upcomingEvents->isNotEmpty())
                <div class="events-list">
                    @foreach($upcomingEvents as $event)
                        <div class="event-item">
                            <div class="event-indicator" style="background-color: {{ $event['color'] ?? 'var(--primary)' }};"></div>
                            <div class="event-content">
                                <div class="event-title">{{ $event['title'] }}</div>
                                <div class="event-meta">
                                    <i class="fas fa-calendar-alt mr-1"></i>
                                    {{ Carbon\Carbon::parse($event['start'])->format('l, F j, Y') }}
                                    @if(isset($event['type']))
                                        <span class="event-separator">•</span>
                                        <span class="event-type">{{ ucfirst(str_replace('_', ' ', $event['type'])) }}</span>
                                    @endif
                                    @if(isset($event['contract_title']))
                                        <span class="event-separator">•</span>
                                        <span class="event-contract">{{ Str::limit(e($event['contract_title']), 30) }}</span>
                                    @endif
                                </div>
                            </div>
                            @if(isset($event['url']))
                                <div class="event-action">
                                    <a href="{{ $event['url'] }}" class="action-btn action-view">
                                        View <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <!-- Empty State -->
                <div class="empty-state" role="status">
                    <div class="empty-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h4 class="empty-title">No upcoming events</h4>
                    <p class="empty-description">You have no upcoming milestones or deadlines in the next 30 days.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Calendar Placeholder -->
    <div class="card table-card">
        <div class="card-header">
            <div class="table-header">
                <h3 class="card-title">
                    <i class="fas fa-calendar-alt mr-2"></i> Full Calendar View
                </h3>
            </div>
        </div>
        <div class="card-body">
            <div class="calendar-placeholder">
                <div class="calendar-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h3 class="calendar-title">Interactive Calendar Coming Soon</h3>
                <p class="calendar-description">
                    A full interactive calendar is being developed. For now, view your upcoming events above.
                </p>
                <div class="calendar-actions">
                    <a href="{{ route('contractor.contracts.index') }}" class="btn btn-primary">
                        <i class="fas fa-file-signature mr-2"></i> View Contracts
                    </a>
                    <a href="{{ route('contractor.projects.active') }}" class="btn btn-outline">
                        <i class="fas fa-tasks mr-2"></i> Active Projects
                    </a>
                </div>
            </div>
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

.page-title .fa-calendar-alt {
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
/* EVENTS LIST */
/* ============================================ */
.events-list {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.75rem;
    max-height: 20rem;
    overflow-y: auto;
    padding-right: 0.25rem;
}

.events-list::-webkit-scrollbar {
    width: 6px;
}

.events-list::-webkit-scrollbar-track {
    background: var(--bg-secondary);
    border-radius: 9999px;
}

.events-list::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 9999px;
}

.events-list::-webkit-scrollbar-thumb:hover {
    background: var(--text-muted);
}

.event-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem 1rem;
    background-color: var(--bg-secondary);
    border-radius: var(--radius);
    transition: all 0.2s ease;
}

.event-item:hover {
    transform: translateX(4px);
    box-shadow: var(--shadow-sm);
}

.event-indicator {
    width: 4px;
    min-height: 3rem;
    border-radius: 9999px;
    flex-shrink: 0;
}

.event-content {
    flex: 1;
    min-width: 0;
}

.event-title {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.125rem;
}

.event-meta {
    font-size: 0.75rem;
    color: var(--text-secondary);
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.25rem;
}

.event-separator {
    color: var(--text-muted);
    padding: 0 0.25rem;
}

.event-type {
    color: var(--text-secondary);
}

.event-contract {
    color: var(--text-secondary);
}

.event-action {
    flex-shrink: 0;
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
    padding: 2rem 1rem;
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
/* CALENDAR PLACEHOLDER */
/* ============================================ */
.calendar-placeholder {
    text-align: center;
    padding: 3rem 1rem;
}

.calendar-icon {
    font-size: 4rem;
    color: var(--text-muted);
    opacity: 0.3;
    margin-bottom: 1.5rem;
}

.calendar-icon i {
    display: block;
}

.calendar-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.calendar-description {
    color: var(--text-secondary);
    max-width: 28rem;
    margin: 0 auto 1.5rem;
}

.calendar-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
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
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .table-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .event-item {
        flex-wrap: wrap;
        padding: 0.75rem;
    }
    
    .event-action {
        width: 100%;
        margin-top: 0.5rem;
    }
    
    .event-action .action-btn {
        width: 100%;
        justify-content: center;
    }
    
    .calendar-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .calendar-actions .btn {
        width: 100%;
        justify-content: center;
    }
    
    .event-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.125rem;
    }
    
    .event-separator {
        display: none;
    }
}

@media (max-width: 480px) {
    .event-item {
        flex-direction: column;
        align-items: stretch;
    }
    
    .event-indicator {
        min-height: 2rem;
        width: 100%;
        height: 4px;
    }
    
    .calendar-icon {
        font-size: 3rem;
    }
    
    .calendar-title {
        font-size: 1.125rem;
    }
    
    .events-list {
        max-height: 15rem;
    }
}
</style>
@endpush
@endsection