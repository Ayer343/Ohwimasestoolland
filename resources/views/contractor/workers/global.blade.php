@extends('layouts.contract')

@section('title', 'All Workers')

@section('content')
<div class="workers-dashboard">
    <!-- Header -->
    <div class="card header-card">
        <div class="header-content">
            <div>
                <h2 class="page-title">
                    <i class="fas fa-users mr-2"></i>
                    All Workers
                </h2>
                <p class="page-subtitle">
                    <i class="fas fa-hard-hat mr-1"></i> 
                    Manage all workers across your contracts
                </p>
            </div>
            <div class="header-actions">
                <a href="{{ route('contractor.contracts.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Contracts
                </a>
                <!-- Add Worker Button - Opens Modal -->
                <button type="button" class="btn btn-primary" onclick="openAddWorkerModal()">
                    <i class="fas fa-plus mr-2"></i> Add Worker
                </button>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        @php
            $statsConfig = [
                'total' => ['icon' => 'fa-users', 'color' => 'primary', 'label' => 'Total Workers'],
                'active' => ['icon' => 'fa-play-circle', 'color' => 'success', 'label' => 'Active'],
                'inactive' => ['icon' => 'fa-pause-circle', 'color' => 'warning', 'label' => 'Inactive'],
                'completed' => ['icon' => 'fa-flag-checkered', 'color' => 'success', 'label' => 'Completed'],
                'terminated' => ['icon' => 'fa-times-circle', 'color' => 'danger', 'label' => 'Terminated'],
                'badge_sent' => ['icon' => 'fa-id-card', 'color' => 'info', 'label' => 'Badges Sent'],
                'badge_active' => ['icon' => 'fa-check-circle', 'color' => 'success', 'label' => 'Active Badges'],
                'site_workers' => ['icon' => 'fa-hard-hat', 'color' => 'primary', 'label' => 'Site Workers'],
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
            <form method="GET" action="{{ route('contractor.workers.global') }}" class="filter-form" role="search">
                @csrf
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="status" class="filter-label">Status</label>
                        <select name="status" id="status" class="form-control" aria-label="Filter by status">
                            <option value="">All Statuses</option>
                            @foreach(['active', 'inactive', 'completed', 'terminated'] as $status)
                                <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                                    {{ ucfirst($status) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="trade" class="filter-label">Trade</label>
                        <select name="trade" id="trade" class="form-control" aria-label="Filter by trade">
                            <option value="">All Trades</option>
                            @foreach($trades as $trade)
                                <option value="{{ e($trade) }}" {{ request('trade') == $trade ? 'selected' : '' }}>
                                    {{ ucfirst(e($trade)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Badge Status Filter -->
                    <div class="filter-group">
                        <label for="badge_status" class="filter-label">Badge Status</label>
                        <select name="badge_status" id="badge_status" class="form-control" aria-label="Filter by badge status">
                            <option value="">All Badge Statuses</option>
                            <option value="has_badge" {{ request('badge_status') == 'has_badge' ? 'selected' : '' }}>
                                Has Badge
                            </option>
                            <option value="no_badge" {{ request('badge_status') == 'no_badge' ? 'selected' : '' }}>
                                No Badge
                            </option>
                            <option value="badge_sent" {{ request('badge_status') == 'badge_sent' ? 'selected' : '' }}>
                                Badge Sent
                            </option>
                            <option value="badge_active" {{ request('badge_status') == 'badge_active' ? 'selected' : '' }}>
                                Active Badge
                            </option>
                            <option value="badge_expired" {{ request('badge_status') == 'badge_expired' ? 'selected' : '' }}>
                                Badge Expired
                            </option>
                        </select>
                    </div>
                    
                    <!-- ✅ Site Assignment Filter -->
                    <div class="filter-group">
                        <label for="site_assignment" class="filter-label">Site Assignment</label>
                        <select name="site_assignment" id="site_assignment" class="form-control" aria-label="Filter by site assignment">
                            <option value="">All Workers</option>
                            <option value="assigned" {{ request('site_assignment') == 'assigned' ? 'selected' : '' }}>
                                <i class="fas fa-hard-hat mr-1"></i> Assigned to Site
                            </option>
                            <option value="unassigned" {{ request('site_assignment') == 'unassigned' ? 'selected' : '' }}>
                                <i class="fas fa-user mr-1"></i> Not Assigned
                            </option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="search" class="filter-label">Search</label>
                        <input type="text" name="search" id="search" 
                               class="form-control"
                               placeholder="Name, job title, trade..."
                               value="{{ e(request('search')) }}"
                               aria-label="Search workers">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                    <a href="{{ route('contractor.workers.global') }}" 
                       class="btn btn-secondary">
                        <i class="fas fa-times mr-2"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Workers Table -->
    <div class="card table-card">
        <div class="card-header">
            <div class="table-header">
                <h3 class="card-title">
                    <i class="fas fa-list mr-2"></i> All Workers
                </h3>
                @if($workers->count() > 0)
                    <div class="table-info" aria-live="polite">
                        Showing {{ $workers->firstItem() }} to {{ $workers->lastItem() }} 
                        of {{ $workers->total() }} workers
                    </div>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if($workers->count() > 0)
                <div class="table-responsive">
                    <table class="workers-table" role="table" aria-label="Workers list">
                        <thead>
                            <tr>
                                <th scope="col">Worker</th>
                                <th scope="col">Contract</th>
                                <th scope="col">Trade</th>
                                <th scope="col">Job Title</th>
                                <th scope="col">Rate</th>
                                <th scope="col">Status</th>
                                <th scope="col">Badge</th>
                                <th scope="col">Site</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($workers as $worker)
                                <tr>
                                    <td>
                                        <div class="worker-info">
                                            <div class="worker-name">
                                                {{ e($worker->full_name) }}
                                            </div>
                                            <div class="worker-contact">
                                                @if($worker->email)
                                                    <span class="contact-item">
                                                        <i class="fas fa-envelope"></i> {{ e($worker->email) }}
                                                    </span>
                                                @endif
                                                @if($worker->phone)
                                                    <span class="contact-item">
                                                        <i class="fas fa-phone"></i> {{ e($worker->phone) }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if($worker->contract)
                                            <div class="contract-info">
                                                <div class="contract-number">
                                                    <a href="{{ route('contractor.contracts.show', $worker->contract->id) }}" 
                                                       class="contract-link">
                                                        {{ e($worker->contract->contract_number) }}
                                                    </a>
                                                </div>
                                                <div class="contract-title">
                                                    {{ Str::limit(e($worker->contract->title), 25) }}
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-muted">No Contract</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($worker->trade)
                                            <span class="trade-badge">
                                                <i class="fas fa-tools mr-1"></i>
                                                {{ ucfirst(e($worker->trade)) }}
                                            </span>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($worker->job_title)
                                            <div class="job-title">{{ e($worker->job_title) }}</div>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($worker->daily_rate)
                                            <div class="rate-amount">
                                                {{ config('app.currency_symbol', '₵') }}{{ number_format($worker->daily_rate, 2) }}
                                                <span class="rate-period">/day</span>
                                            </div>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $statusConfig = [
                                                'active' => ['color' => 'success', 'icon' => 'fa-play-circle'],
                                                'inactive' => ['color' => 'warning', 'icon' => 'fa-pause-circle'],
                                                'completed' => ['color' => 'success', 'icon' => 'fa-flag-checkered'],
                                                'terminated' => ['color' => 'danger', 'icon' => 'fa-times-circle'],
                                            ];
                                            $config = $statusConfig[$worker->status] ?? ['color' => 'secondary', 'icon' => 'fa-question-circle'];
                                        @endphp
                                        <span class="status-badge status-{{ $config['color'] }}" role="status">
                                            <i class="fas {{ $config['icon'] }} mr-1"></i>
                                            {{ ucfirst($worker->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($worker->badge)
                                            @php
                                                $badgeStatus = $worker->badge->status;
                                                $badgeColors = [
                                                    'active' => 'success',
                                                    'expired' => 'danger',
                                                    'inactive' => 'secondary',
                                                ];
                                                $badgeIcons = [
                                                    'active' => 'fa-check-circle',
                                                    'expired' => 'fa-exclamation-circle',
                                                    'inactive' => 'fa-times-circle',
                                                ];
                                                $badgeColor = $badgeColors[$badgeStatus] ?? 'secondary';
                                                $badgeIcon = $badgeIcons[$badgeStatus] ?? 'fa-question-circle';
                                            @endphp
                                            <div class="badge-info">
                                                <span class="badge-number">{{ $worker->badge->badge_number }}</span>
                                                <span class="status-badge status-{{ $badgeColor }}" role="status" style="font-size: 0.65rem; padding: 0.15rem 0.5rem;">
                                                    <i class="fas {{ $badgeIcon }} mr-1"></i>
                                                    {{ ucfirst($badgeStatus) }}
                                                </span>
                                                @if($worker->badge->valid_until)
                                                    <div class="badge-expiry" style="font-size: 0.65rem; color: var(--text-muted);">
                                                        <i class="fas fa-calendar-alt mr-1"></i>
                                                        {{ $worker->badge->valid_until->format('d M Y') }}
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-muted" style="font-size: 0.75rem;">No badge</span>
                                        @endif
                                    </td>
                                    <!-- ✅ Site Assignment Column -->
                                    <td>
                                        @if($worker->contract && $worker->status === 'active')
                                            <div class="site-assignment-toggle">
                                                <button type="button" 
                                                        class="site-toggle-btn {{ $worker->is_assigned_to_site ? 'assigned' : 'unassigned' }}"
                                                        data-worker-id="{{ $worker->id }}"
                                                        data-contract-id="{{ $worker->contract->id }}"
                                                        onclick="toggleSiteAssignment({{ $worker->id }}, {{ $worker->contract->id }})"
                                                        title="{{ $worker->is_assigned_to_site ? 'Click to remove from site' : 'Click to assign to site' }}">
                                                    @if($worker->is_assigned_to_site)
                                                        <i class="fas fa-check-circle"></i>
                                                        <span class="status-label">Assigned</span>
                                                    @else
                                                        <i class="fas fa-plus-circle"></i>
                                                        <span class="status-label">Assign</span>
                                                    @endif
                                                </button>
                                                @if($worker->is_assigned_to_site)
                                                    <div class="site-info">
                                                        <i class="fas fa-map-marker-alt"></i>
                                                        <span>{{ $worker->contract->location ?? 'Site' }}</span>
                                                        <button type="button" 
                                                                class="unassign-btn" 
                                                                onclick="toggleSiteAssignment({{ $worker->id }}, {{ $worker->contract->id }})"
                                                                title="Unassign from site">
                                                            <i class="fas fa-times-circle"></i>
                                                        </button>
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-muted" style="font-size: 0.75rem;">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            @if($worker->contract)
                                                <!-- View Details - Opens Modal -->
                                                <button type="button" 
                                                        class="action-btn action-view" 
                                                        title="View Details"
                                                        aria-label="View details for {{ e($worker->full_name) }}"
                                                        onclick="openViewWorkerModal({{ $worker->id }}, {{ $worker->contract->id }})">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <!-- Edit Worker - Opens Modal -->
                                                <button type="button" 
                                                        class="action-btn action-edit" 
                                                        title="Edit Worker"
                                                        aria-label="Edit {{ e($worker->full_name) }}"
                                                        onclick="openEditWorkerModal({{ $worker->id }}, {{ $worker->contract->id }})">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <!-- Send Badge Button -->
                                                @if($worker->status === 'active' && $worker->email)
                                                    @if($worker->badge)
                                                        <button type="button" 
                                                                class="action-btn action-badge-resend" 
                                                                title="Resend Badge"
                                                                aria-label="Resend badge to {{ e($worker->full_name) }}"
                                                                onclick="sendBadge({{ $worker->id }}, {{ $worker->contract->id }}, 'resend')">
                                                            <i class="fas fa-envelope"></i>
                                                        </button>
                                                    @else
                                                        <button type="button" 
                                                                class="action-btn action-badge-send" 
                                                                title="Send Badge"
                                                                aria-label="Send badge to {{ e($worker->full_name) }}"
                                                                onclick="sendBadge({{ $worker->id }}, {{ $worker->contract->id }}, 'send')">
                                                            <i class="fas fa-id-card"></i>
                                                        </button>
                                                    @endif
                                                @endif
                                                <!-- View Contract -->
                                                <a href="{{ route('contractor.contracts.show', $worker->contract->id) }}" 
                                                   class="action-btn action-contract" 
                                                   title="View Contract"
                                                   aria-label="View contract for {{ e($worker->full_name) }}">
                                                    <i class="fas fa-file-contract"></i>
                                                </a>
                                            @else
                                                <span class="text-muted">No actions</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($workers->hasPages())
                    <div class="pagination-container">
                        {{ $workers->appends(request()->query())->links() }}
                    </div>
                @endif
            @else
                <!-- Empty State -->
                <div class="empty-state" role="status">
                    <div class="empty-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h4 class="empty-title">No workers found</h4>
                    <p class="empty-description">You haven't added any workers to your contracts yet</p>
                    @if(request()->hasAny(['status', 'trade', 'badge_status', 'site_assignment', 'search']))
                        <a href="{{ route('contractor.workers.global') }}" class="btn btn-primary mt-4">
                            <i class="fas fa-times mr-2"></i> Clear All Filters
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- VIEW WORKER DETAILS MODAL (UPDATED) -->
<!-- ============================================ -->
<div id="viewWorkerModal" class="custom-modal">
    <div class="custom-modal-overlay" onclick="closeViewWorkerModal()"></div>
    <div class="custom-modal-dialog custom-modal-lg">
        <div class="custom-modal-content theme-aware">
            <div class="custom-modal-header">
                <h5 class="custom-modal-title">
                    <i class="fas fa-user mr-2"></i> Worker Details
                </h5>
                <button type="button" class="custom-modal-close" onclick="closeViewWorkerModal()" aria-label="Close">
                    &times;
                </button>
            </div>
            <div class="custom-modal-body">
                <!-- Loading State -->
                <div id="viewWorkerLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading worker details...</p>
                </div>
                
                <!-- Worker Details Content -->
                <div id="viewWorkerContent" style="display: none;">
                    <div class="worker-profile">
                        <!-- Header with Name and Status -->
                        <div class="worker-profile-header">
                            <div class="worker-avatar">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div class="worker-header-info">
                                <h3 id="viewWorkerName" class="worker-name-display">-</h3>
                                <div class="worker-header-meta">
                                    <span id="viewWorkerStatus" class="status-badge">-</span>
                                    <span id="viewWorkerTrade" class="trade-badge">-</span>
                                    <span id="viewWorkerBadgeStatus" class="status-badge" style="display: none;">-</span>
                                    <span id="viewWorkerSiteStatus" class="status-badge" style="display: none;">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Details Grid -->
                        <div class="worker-details-grid">
                            <!-- Personal Information -->
                            <div class="detail-section">
                                <h6 class="section-title">
                                    <i class="fas fa-id-card mr-2"></i> Personal Information
                                </h6>
                                <div class="detail-row">
                                    <span class="detail-label">Full Name</span>
                                    <span id="viewFullName" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Email</span>
                                    <span id="viewEmail" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Phone</span>
                                    <span id="viewPhone" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">ID Number</span>
                                    <span id="viewIdNumber" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Address</span>
                                    <span id="viewAddress" class="detail-value">-</span>
                                </div>
                            </div>

                            <!-- Employment Details -->
                            <div class="detail-section">
                                <h6 class="section-title">
                                    <i class="fas fa-briefcase mr-2"></i> Employment Details
                                </h6>
                                <div class="detail-row">
                                    <span class="detail-label">Job Title</span>
                                    <span id="viewJobTitle" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Trade</span>
                                    <span id="viewTrade" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Specialization</span>
                                    <span id="viewSpecialization" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Contract</span>
                                    <span id="viewContract" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Status</span>
                                    <span id="viewStatus" class="detail-value">-</span>
                                </div>
                            </div>

                            <!-- Financial Details -->
                            <div class="detail-section">
                                <h6 class="section-title">
                                    <i class="fas fa-money-bill-wave mr-2"></i> Financial Details
                                </h6>
                                <div class="detail-row">
                                    <span class="detail-label">Daily Rate</span>
                                    <span id="viewDailyRate" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Contract Rate</span>
                                    <span id="viewContractRate" class="detail-value">-</span>
                                </div>
                            </div>

                            <!-- Dates -->
                            <div class="detail-section">
                                <h6 class="section-title">
                                    <i class="fas fa-calendar-alt mr-2"></i> Dates
                                </h6>
                                <div class="detail-row">
                                    <span class="detail-label">Start Date</span>
                                    <span id="viewStartDate" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">End Date</span>
                                    <span id="viewEndDate" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Added By</span>
                                    <span id="viewAddedBy" class="detail-value">-</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Added On</span>
                                    <span id="viewAddedOn" class="detail-value">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Badge Details Section -->
                        <div id="viewBadgeSection" class="detail-section" style="display: none;">
                            <h6 class="section-title">
                                <i class="fas fa-id-card mr-2"></i> Badge Details
                            </h6>
                            <div class="detail-row">
                                <span class="detail-label">Badge Number</span>
                                <span id="viewBadgeNumber" class="detail-value">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Badge Status</span>
                                <span id="viewBadgeStatus" class="detail-value">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Valid From</span>
                                <span id="viewBadgeValidFrom" class="detail-value">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Valid Until</span>
                                <span id="viewBadgeValidUntil" class="detail-value">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Verification Count</span>
                                <span id="viewBadgeVerificationCount" class="detail-value">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Last Verified</span>
                                <span id="viewBadgeLastVerified" class="detail-value">-</span>
                            </div>
                            <div class="detail-row" id="viewBadgeVerificationUrlRow">
                                <span class="detail-label">Verification URL</span>
                                <span id="viewBadgeVerificationUrl" class="detail-value">
                                    <a href="#" target="_blank" class="text-primary">View Badge</a>
                                </span>
                            </div>
                        </div>

                        <!-- ✅ Site Assignment Details -->
                        <div id="viewSiteAssignmentSection" class="detail-section" style="display: none;">
                            <h6 class="section-title">
                                <i class="fas fa-hard-hat mr-2"></i> Site Assignment
                            </h6>
                            <div class="detail-row">
                                <span class="detail-label">Site Status</span>
                                <span id="viewSiteStatus" class="detail-value">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Assigned To</span>
                                <span id="viewSiteLocation" class="detail-value">-</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Assigned On</span>
                                <span id="viewSiteAssignedOn" class="detail-value">-</span>
                            </div>
                        </div>

                        <!-- Skills Section -->
                        <div id="viewSkillsSection" class="detail-section" style="display: none;">
                            <h6 class="section-title">
                                <i class="fas fa-tools mr-2"></i> Skills
                            </h6>
                            <div id="viewSkills" class="skills-container">
                                <!-- Skills will be rendered here -->
                            </div>
                        </div>

                        <!-- Description & Notes -->
                        <div id="viewDescriptionSection" class="detail-section" style="display: none;">
                            <h6 class="section-title">
                                <i class="fas fa-file-alt mr-2"></i> Service Description
                            </h6>
                            <p id="viewServiceDescription" class="detail-text">-</p>
                        </div>

                        <div id="viewNotesSection" class="detail-section" style="display: none;">
                            <h6 class="section-title">
                                <i class="fas fa-sticky-note mr-2"></i> Notes
                            </h6>
                            <p id="viewNotes" class="detail-text">-</p>
                        </div>
                    </div>
                </div>

                <!-- Error State -->
                <div id="viewWorkerError" class="text-center py-5" style="display: none;">
                    <div class="empty-icon">
                        <i class="fas fa-exclamation-circle text-danger"></i>
                    </div>
                    <h4 class="empty-title">Failed to load worker details</h4>
                    <p id="viewWorkerErrorMessage" class="text-muted">Please try again.</p>
                    <button type="button" class="btn btn-primary mt-3" onclick="closeViewWorkerModal()">
                        <i class="fas fa-times mr-2"></i> Close
                    </button>
                </div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" id="viewSendBadgeBtn" class="btn btn-info" style="display: none;">
                    <i class="fas fa-id-card mr-2"></i> Send Badge
                </button>
                <button type="button" id="viewToggleSiteBtn" class="btn btn-success" style="display: none;">
                    <i class="fas fa-hard-hat mr-2"></i> Assign to Site
                </button>
                <button type="button" id="viewUnassignSiteBtn" class="btn btn-danger" style="display: none;">
                    <i class="fas fa-times mr-2"></i> Remove from Site
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeViewWorkerModal()">
                    <i class="fas fa-times mr-2"></i> Close
                </button>
                <button type="button" id="viewEditWorkerBtn" class="btn btn-primary" style="display: none;">
                    <i class="fas fa-edit mr-2"></i> Edit Worker
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- EDIT WORKER MODAL (UPDATED) -->
<!-- ============================================ -->
<div id="editWorkerModal" class="custom-modal">
    <div class="custom-modal-overlay" onclick="closeEditWorkerModal()"></div>
    <div class="custom-modal-dialog custom-modal-lg">
        <div class="custom-modal-content theme-aware">
            <div class="custom-modal-header">
                <h5 class="custom-modal-title">
                    <i class="fas fa-user-edit mr-2"></i> Edit Worker
                </h5>
                <button type="button" class="custom-modal-close" onclick="closeEditWorkerModal()" aria-label="Close">
                    &times;
                </button>
            </div>
            <div class="custom-modal-body">
                <!-- Loading State -->
                <div id="editWorkerLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading worker details...</p>
                </div>
                
                <!-- Edit Form -->
                <div id="editWorkerFormContainer" style="display: none;">
                    <form id="editWorkerForm" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" id="edit_contract_id" name="contract_id" value="">
                        <input type="hidden" id="edit_worker_id" name="worker_id" value="">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_full_name" class="form-label required">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="full_name" id="edit_full_name" class="form-control" 
                                           placeholder="Enter worker's full name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_job_title" class="form-label">Job Title</label>
                                    <input type="text" name="job_title" id="edit_job_title" class="form-control" 
                                           placeholder="e.g., Senior Electrician">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_email" class="form-label">Email</label>
                                    <input type="email" name="email" id="edit_email" class="form-control" 
                                           placeholder="worker@example.com">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_phone" class="form-label">Phone</label>
                                    <input type="text" name="phone" id="edit_phone" class="form-control" 
                                           placeholder="+233 XX XXX XXXX">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_trade" class="form-label">Trade</label>
                                    <select name="trade" id="edit_trade" class="form-control">
                                        <option value="">Select Trade</option>
                                        <option value="electrical">Electrical</option>
                                        <option value="plumbing">Plumbing</option>
                                        <option value="carpentry">Carpentry</option>
                                        <option value="masonry">Masonry</option>
                                        <option value="welding">Welding</option>
                                        <option value="painting">Painting</option>
                                        <option value="roofing">Roofing</option>
                                        <option value="hvac">HVAC</option>
                                        <option value="general">General Labor</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_specialization" class="form-label">Specialization</label>
                                    <input type="text" name="specialization" id="edit_specialization" class="form-control" 
                                           placeholder="e.g., Residential Wiring">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="edit_daily_rate" class="form-label">Daily Rate ({{ config('app.currency_symbol', '₵') }})</label>
                                    <input type="number" name="daily_rate" id="edit_daily_rate" class="form-control" 
                                           placeholder="0.00" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="edit_contract_rate" class="form-label">Contract Rate ({{ config('app.currency_symbol', '₵') }})</label>
                                    <input type="number" name="contract_rate" id="edit_contract_rate" class="form-control" 
                                           placeholder="0.00" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="edit_status" class="form-label required">Status <span class="text-danger">*</span></label>
                                    <select name="status" id="edit_status" class="form-control" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="completed">Completed</option>
                                        <option value="terminated">Terminated</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_id_number" class="form-label">ID Number</label>
                                    <input type="text" name="id_number" id="edit_id_number" class="form-control" 
                                           placeholder="National ID / Passport">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_address" class="form-label">Address</label>
                                    <input type="text" name="address" id="edit_address" class="form-control" 
                                           placeholder="Physical address">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_start_date" class="form-label">Start Date</label>
                                    <input type="date" name="start_date" id="edit_start_date" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_end_date" class="form-label">End Date</label>
                                    <input type="date" name="end_date" id="edit_end_date" class="form-control">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_service_description" class="form-label">Service Description</label>
                            <textarea name="service_description" id="edit_service_description" class="form-control" 
                                      rows="2" placeholder="Describe the services this worker will provide"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_notes" class="form-label">Notes</label>
                            <textarea name="notes" id="edit_notes" class="form-control" 
                                      rows="2" placeholder="Any additional notes about this worker"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_skills" class="form-label">Skills</label>
                            <select name="skills[]" id="edit_skills" class="form-control" multiple>
                                <option value="carpentry">Carpentry</option>
                                <option value="drywall">Drywall</option>
                                <option value="electrical">Electrical</option>
                                <option value="painting">Painting</option>
                                <option value="plumbing">Plumbing</option>
                                <option value="roofing">Roofing</option>
                                <option value="tiling">Tiling</option>
                                <option value="welding">Welding</option>
                                <option value="masonry">Masonry</option>
                                <option value="hvac">HVAC</option>
                                <option value="landscaping">Landscaping</option>
                                <option value="concrete">Concrete Work</option>
                            </select>
                            <small class="form-text text-muted">Hold Ctrl (or Cmd on Mac) to select multiple skills</small>
                        </div>

                        <!-- Badge Options -->
                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" name="send_badge" id="edit_send_badge" value="1" class="form-check-input">
                                <label for="edit_send_badge" class="form-check-label">
                                    <i class="fas fa-id-card mr-1"></i> Send badge to worker after update
                                </label>
                                <small class="form-text text-muted d-block">Check this to send a new badge to the worker's email</small>
                            </div>
                        </div>

                        <!-- ✅ Site Assignment Toggle -->
                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" name="assign_to_site" id="edit_assign_to_site" value="1" class="form-check-input">
                                <label for="edit_assign_to_site" class="form-check-label">
                                    <i class="fas fa-hard-hat mr-1"></i> Assign to site
                                </label>
                                <small class="form-text text-muted d-block">Assign this worker to the contract site location</small>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Error State -->
                <div id="editWorkerError" class="text-center py-5" style="display: none;">
                    <div class="empty-icon">
                        <i class="fas fa-exclamation-circle text-danger"></i>
                    </div>
                    <h4 class="empty-title">Failed to load worker details</h4>
                    <p id="editWorkerErrorMessage" class="text-muted">Please try again.</p>
                    <button type="button" class="btn btn-primary mt-3" onclick="closeEditWorkerModal()">
                        <i class="fas fa-times mr-2"></i> Close
                    </button>
                </div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditWorkerModal()">
                    <i class="fas fa-times mr-2"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="submitEditWorkerForm()">
                    <i class="fas fa-save mr-2"></i> Update Worker
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- ADD WORKER MODAL (UPDATED) -->
<!-- ============================================ -->
<div id="addWorkerModal" class="custom-modal">
    <div class="custom-modal-overlay" onclick="closeAddWorkerModal()"></div>
    <div class="custom-modal-dialog custom-modal-lg">
        <div class="custom-modal-content theme-aware">
            <div class="custom-modal-header">
                <h5 class="custom-modal-title">
                    <i class="fas fa-user-plus mr-2"></i> Add New Worker
                </h5>
                <button type="button" class="custom-modal-close" onclick="closeAddWorkerModal()" aria-label="Close">
                    &times;
                </button>
            </div>
            <div class="custom-modal-body">
                <!-- Step 1: Select Contract -->
                <div id="stepContractSelect">
                    <div class="step-indicator">
                        <span class="step-badge active">1</span>
                        <span class="step-label">Select Contract</span>
                        <span class="step-divider">—</span>
                        <span class="step-badge">2</span>
                        <span class="step-label">Worker Details</span>
                    </div>
                    
                    <div class="contract-selection-section">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-2"></i>
                            Select a contract to add a worker to. Only your active contracts are shown.
                        </div>
                        
                        <div class="contract-search-box">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="modalContractSearch" class="form-control" 
                                       placeholder="Search your contracts by number or title..." 
                                       aria-label="Search contracts"
                                       onkeyup="filterContracts()">
                            </div>
                        </div>
                        
                        <div class="contract-list-container" id="modalContractList">
                            @php
                                $contracts = App\Models\ConstructionContract::where('contractor_user_id', auth()->id())
                                    ->whereIn('status', ['approved', 'in_progress'])
                                    ->orderBy('created_at', 'desc')
                                    ->get();
                            @endphp
                            
                            @forelse($contracts as $contract)
                                <div class="contract-item" data-contract-id="{{ $contract->id }}" 
                                     data-search="{{ strtolower($contract->contract_number . ' ' . $contract->title) }}">
                                    <div class="contract-item-content">
                                        <div class="contract-item-left">
                                            <div class="contract-item-number">
                                                <i class="fas fa-file-contract"></i>
                                                {{ e($contract->contract_number) }}
                                            </div>
                                            <div class="contract-item-title">
                                                {{ e($contract->title) }}
                                            </div>
                                            <div class="contract-item-meta">
                                                <span class="meta-tag">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    {{ e($contract->location ?? 'Location not specified') }}
                                                </span>
                                                <span class="meta-tag">
                                                    <i class="fas fa-calendar-alt"></i>
                                                    {{ $contract->start_date ? $contract->start_date->format('M d, Y') : 'TBD' }}
                                                </span>
                                                <span class="meta-tag status-tag-{{ strtolower(str_replace(' ', '_', $contract->status)) }}">
                                                    <i class="fas fa-circle"></i>
                                                    {{ ucfirst(str_replace('_', ' ', $contract->status)) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="contract-item-right">
                                            <button type="button" class="btn btn-primary btn-sm select-contract-btn" 
                                                    data-contract-id="{{ $contract->id }}"
                                                    data-contract-number="{{ e($contract->contract_number) }}"
                                                    onclick="selectContract(this)">
                                                <i class="fas fa-arrow-right mr-1"></i> Select
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="empty-contracts">
                                    <i class="fas fa-file-contract fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">You don't have any active contracts available</p>
                                    <a href="{{ route('contractor.contracts.create') }}" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus mr-2"></i> Create Contract
                                    </a>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
                
                <!-- Step 2: Worker Details Form (Hidden initially) -->
                <div id="stepWorkerForm" style="display: none;">
                    <div class="step-indicator">
                        <span class="step-badge completed">✓</span>
                        <span class="step-label completed-label">Contract Selected</span>
                        <span class="step-divider">—</span>
                        <span class="step-badge active">2</span>
                        <span class="step-label">Worker Details</span>
                    </div>
                    
                    <div class="selected-contract-summary" id="selectedContractSummary">
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle mr-2"></i>
                            Adding worker to: <strong id="selectedContractNumber"></strong>
                            <button type="button" class="btn btn-link btn-sm float-end" onclick="goBackToContracts()">
                                <i class="fas fa-edit"></i> Change Contract
                            </button>
                        </div>
                    </div>
                    
                    <form id="workerForm" method="POST">
                        @csrf
                        <input type="hidden" id="contract_id" name="contract_id" value="">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="full_name" class="form-label required">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="full_name" id="full_name" class="form-control" 
                                           placeholder="Enter worker's full name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="job_title" class="form-label">Job Title</label>
                                    <input type="text" name="job_title" id="job_title" class="form-control" 
                                           placeholder="e.g., Senior Electrician">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" name="email" id="email" class="form-control" 
                                           placeholder="worker@example.com">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="text" name="phone" id="phone" class="form-control" 
                                           placeholder="+233 XX XXX XXXX">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="trade" class="form-label">Trade</label>
                                    <select name="trade" id="trade" class="form-control">
                                        <option value="">Select Trade</option>
                                        <option value="electrical">Electrical</option>
                                        <option value="plumbing">Plumbing</option>
                                        <option value="carpentry">Carpentry</option>
                                        <option value="masonry">Masonry</option>
                                        <option value="welding">Welding</option>
                                        <option value="painting">Painting</option>
                                        <option value="roofing">Roofing</option>
                                        <option value="hvac">HVAC</option>
                                        <option value="general">General Labor</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="specialization" class="form-label">Specialization</label>
                                    <input type="text" name="specialization" id="specialization" class="form-control" 
                                           placeholder="e.g., Residential Wiring">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="daily_rate" class="form-label">Daily Rate ({{ config('app.currency_symbol', '₵') }})</label>
                                    <input type="number" name="daily_rate" id="daily_rate" class="form-control" 
                                           placeholder="0.00" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="contract_rate" class="form-label">Contract Rate ({{ config('app.currency_symbol', '₵') }})</label>
                                    <input type="number" name="contract_rate" id="contract_rate" class="form-control" 
                                           placeholder="0.00" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="status" class="form-label required">Status <span class="text-danger">*</span></label>
                                    <select name="status" id="status" class="form-control" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="id_number" class="form-label">ID Number</label>
                                    <input type="text" name="id_number" id="id_number" class="form-control" 
                                           placeholder="National ID / Passport">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="address" class="form-label">Address</label>
                                    <input type="text" name="address" id="address" class="form-control" 
                                           placeholder="Physical address">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" name="start_date" id="start_date" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" name="end_date" id="end_date" class="form-control">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="service_description" class="form-label">Service Description</label>
                            <textarea name="service_description" id="service_description" class="form-control" 
                                      rows="2" placeholder="Describe the services this worker will provide"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea name="notes" id="notes" class="form-control" 
                                      rows="2" placeholder="Any additional notes about this worker"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="skills" class="form-label">Skills</label>
                            <select name="skills[]" id="skills" class="form-control" multiple>
                                <option value="carpentry">Carpentry</option>
                                <option value="drywall">Drywall</option>
                                <option value="electrical">Electrical</option>
                                <option value="painting">Painting</option>
                                <option value="plumbing">Plumbing</option>
                                <option value="roofing">Roofing</option>
                                <option value="tiling">Tiling</option>
                                <option value="welding">Welding</option>
                                <option value="masonry">Masonry</option>
                                <option value="hvac">HVAC</option>
                                <option value="landscaping">Landscaping</option>
                                <option value="concrete">Concrete Work</option>
                            </select>
                            <small class="form-text text-muted">Hold Ctrl (or Cmd on Mac) to select multiple skills</small>
                        </div>

                        <!-- Badge Options -->
                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" name="send_badge" id="send_badge" value="1" checked class="form-check-input">
                                <label for="send_badge" class="form-check-label">
                                    <i class="fas fa-id-card mr-1"></i> Send badge to worker after creation
                                </label>
                                <small class="form-text text-muted d-block">A digital badge will be sent to the worker's email if provided and worker is active</small>
                            </div>
                        </div>

                        <!-- ✅ Site Assignment -->
                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" name="assign_to_site" id="assign_to_site" value="1" checked class="form-check-input">
                                <label for="assign_to_site" class="form-check-label">
                                    <i class="fas fa-hard-hat mr-1"></i> Assign to site
                                </label>
                                <small class="form-text text-muted d-block">Assign this worker to the contract site location</small>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddWorkerModal()">Cancel</button>
                <button type="button" class="btn btn-secondary" id="backToContractsBtn" style="display: none;" onclick="goBackToContracts()">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </button>
                <button type="button" class="btn btn-primary" id="submitWorkerBtn" style="display: none;" onclick="submitWorkerForm()">
                    <i class="fas fa-save mr-2"></i> Add Worker
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// ============================================ //
// SITE ASSIGNMENT TOGGLE //
// ============================================ //

function toggleSiteAssignment(workerId, contractId) {
    // Show confirmation
    var btn = document.querySelector(`.site-toggle-btn[data-worker-id="${workerId}"]`);
    var isAssigned = btn ? btn.classList.contains('assigned') : false;
    var action = isAssigned ? 'remove from' : 'assign to';
    
    if (!confirm(`Are you sure you want to ${action} site for this worker?`)) {
        return;
    }
    
    // Show loading state
    var originalHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Updating...';
        btn.disabled = true;
    }
    
    // ✅ Use the correct URL format - matches the route
    var url = "/contractor/contracts/" + contractId + "/workers/" + workerId + "/toggle-site";
    
    fetch(url, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        credentials: 'same-origin'
    })
    .then(function(response) {
        // ✅ Handle 405 Method Not Allowed
        if (response.status === 405) {
            throw new Error('Route not found or method not allowed. Please check the route configuration.');
        }
        
        if (!response.ok) {
            return response.text().then(function(text) {
                // ✅ Remove BOM and other invisible characters
                var cleanText = text.replace(/^\uFEFF/, '').replace(/^\u00BB\uBF/, '').replace(/^\uFFFE/, '');
                if (cleanText.charCodeAt(0) === 65279) {
                    cleanText = cleanText.substring(1);
                }
                try {
                    var errorData = JSON.parse(cleanText);
                    throw new Error(errorData.message || 'Server returned ' + response.status);
                } catch (e) {
                    throw new Error('Server returned ' + response.status + ': ' + cleanText.substring(0, 100));
                }
            });
        }
        return response.text();
    })
    .then(function(text) {
        // ✅ Remove BOM and other invisible characters from response
        var cleanText = text.replace(/^\uFEFF/, '').replace(/^\u00BB\uBF/, '').replace(/^\uFFFE/, '');
        if (cleanText.charCodeAt(0) === 65279) {
            cleanText = cleanText.substring(1);
        }
        
        var data;
        try {
            data = JSON.parse(cleanText);
        } catch (parseError) {
            // ✅ If parsing fails, try to clean more aggressively
            var cleaned = cleanText.replace(/[^\x20-\x7E]/g, '').trim();
            try {
                data = JSON.parse(cleaned);
            } catch (e) {
                throw new Error('Invalid response format from server: ' + cleanText.substring(0, 100));
            }
        }
        
        if (btn) {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
        
        if (data.success) {
            showNotification(data.message || 'Site assignment updated!', 'success');
            
            // ✅ Update button state without reload
            if (data.is_assigned) {
                if (btn) {
                    btn.className = 'site-toggle-btn assigned';
                    btn.innerHTML = '<i class="fas fa-check-circle"></i><span class="status-label">Assigned</span>';
                    // Add unassign button to site info
                    var parent = btn.parentElement;
                    var siteInfo = parent.querySelector('.site-info');
                    if (!siteInfo) {
                        siteInfo = document.createElement('div');
                        siteInfo.className = 'site-info';
                        siteInfo.innerHTML = '<i class="fas fa-map-marker-alt"></i><span>Site</span><button type="button" class="unassign-btn" onclick="toggleSiteAssignment(' + workerId + ', ' + contractId + ')"><i class="fas fa-times-circle"></i></button>';
                        parent.appendChild(siteInfo);
                    }
                }
                // Update the view modal if open
                updateViewModalSiteStatus(true);
            } else {
                if (btn) {
                    btn.className = 'site-toggle-btn unassigned';
                    btn.innerHTML = '<i class="fas fa-plus-circle"></i><span class="status-label">Assign</span>';
                    // Remove site info
                    var parent = btn.parentElement;
                    var siteInfo = parent.querySelector('.site-info');
                    if (siteInfo) {
                        siteInfo.remove();
                    }
                }
                updateViewModalSiteStatus(false);
            }
            
            // Reload after a moment to refresh all data
            setTimeout(function() {
                location.reload();
            }, 1500);
        } else {
            showNotification(data.message || 'Failed to update site assignment.', 'error');
        }
    })
    .catch(function(error) {
        if (btn) {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        }
        console.error('Error toggling site assignment:', error);
        showNotification('Error: ' + error.message, 'error');
    });
}

// ============================================ //
// UPDATE VIEW MODAL SITE STATUS //
// ============================================ //

function updateViewModalSiteStatus(isAssigned) {
    var siteStatusEl = document.getElementById('viewWorkerSiteStatus');
    var viewSiteStatus = document.getElementById('viewSiteStatus');
    var viewToggleBtn = document.getElementById('viewToggleSiteBtn');
    var viewUnassignBtn = document.getElementById('viewUnassignSiteBtn');
    
    if (siteStatusEl) {
        siteStatusEl.style.display = 'inline-flex';
        siteStatusEl.className = 'status-badge status-' + (isAssigned ? 'success' : 'warning');
        siteStatusEl.innerHTML = '<i class="fas fa-hard-hat mr-1"></i> ' + (isAssigned ? 'On Site' : 'Not Assigned');
    }
    
    if (viewSiteStatus) {
        if (isAssigned) {
            viewSiteStatus.innerHTML = '<span class="status-badge status-success"><i class="fas fa-check-circle mr-1"></i> Assigned to Site</span>';
        } else {
            viewSiteStatus.innerHTML = '<span class="status-badge status-warning"><i class="fas fa-clock mr-1"></i> Not Assigned</span>';
        }
    }
    
    if (viewToggleBtn) {
        if (isAssigned) {
            viewToggleBtn.style.display = 'none';
        } else {
            viewToggleBtn.style.display = 'inline-flex';
            viewToggleBtn.innerHTML = '<i class="fas fa-hard-hat mr-2"></i> Assign to Site';
            viewToggleBtn.className = 'btn btn-success';
        }
    }
    
    if (viewUnassignBtn) {
        if (isAssigned) {
            viewUnassignBtn.style.display = 'inline-flex';
            viewUnassignBtn.innerHTML = '<i class="fas fa-times mr-2"></i> Remove from Site';
            viewUnassignBtn.className = 'btn btn-danger';
        } else {
            viewUnassignBtn.style.display = 'none';
        }
    }
}

// ============================================ //
// VIEW WORKER MODAL FUNCTIONS (UPDATED) //
// ============================================ //

function openViewWorkerModal(workerId, contractId) {
    var modal = document.getElementById('viewWorkerModal');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    document.getElementById('viewWorkerLoading').style.display = 'block';
    document.getElementById('viewWorkerContent').style.display = 'none';
    document.getElementById('viewWorkerError').style.display = 'none';
    
    fetchWorkerDetails(workerId, contractId);
}

function closeViewWorkerModal() {
    document.getElementById('viewWorkerModal').classList.remove('show');
    document.body.style.overflow = '';
}

function fetchWorkerDetails(workerId, contractId) {
    var url = "/contractor/contracts/" + contractId + "/workers/" + workerId;
    
    fetch(url, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        credentials: 'same-origin'
    })
    .then(function(response) {
        if (!response.ok) {
            return response.text().then(function(text) {
                throw new Error('Server returned ' + response.status + ': ' + text.substring(0, 100));
            });
        }
        return response.text();
    })
    .then(function(text) {
        // ✅ Remove BOM and other invisible characters
        var cleanedText = text.replace(/^\uFEFF/, '').replace(/^\u00BB\uBF/, '').replace(/^\uFFFE/, '');
        if (cleanedText.charCodeAt(0) === 65279) {
            cleanedText = cleanedText.substring(1);
        }
        
        try {
            var data = JSON.parse(cleanedText);
            if (data.success) {
                displayWorkerDetails(data.worker);
            } else {
                showWorkerError(data.message || 'Failed to load worker details.');
            }
        } catch (parseError) {
            showWorkerError('Invalid response format from server.');
        }
    })
    .catch(function(error) {
        showWorkerError('An error occurred while loading worker details: ' + error.message);
    });
}

function displayWorkerDetails(worker) {
    document.getElementById('viewWorkerLoading').style.display = 'none';
    document.getElementById('viewWorkerContent').style.display = 'block';
    document.getElementById('viewWorkerError').style.display = 'none';
    
    document.getElementById('viewWorkerName').textContent = worker.full_name || '-';
    document.getElementById('viewFullName').textContent = worker.full_name || '-';
    document.getElementById('viewEmail').textContent = worker.email || 'Not provided';
    document.getElementById('viewPhone').textContent = worker.phone || 'Not provided';
    document.getElementById('viewIdNumber').textContent = worker.id_number || 'Not provided';
    document.getElementById('viewAddress').textContent = worker.address || 'Not provided';
    document.getElementById('viewJobTitle').textContent = worker.job_title || 'Not specified';
    document.getElementById('viewTrade').textContent = worker.trade ? ucfirst(worker.trade) : 'Not specified';
    document.getElementById('viewSpecialization').textContent = worker.specialization || 'Not specified';
    document.getElementById('viewDailyRate').textContent = worker.daily_rate ? '₵' + parseFloat(worker.daily_rate).toFixed(2) + ' / day' : 'Not set';
    document.getElementById('viewContractRate').textContent = worker.contract_rate ? '₵' + parseFloat(worker.contract_rate).toFixed(2) : 'Not set';
    document.getElementById('viewStartDate').textContent = worker.start_date ? formatDate(worker.start_date) : 'Not set';
    document.getElementById('viewEndDate').textContent = worker.end_date ? formatDate(worker.end_date) : 'Not set';
    document.getElementById('viewAddedOn').textContent = worker.created_at ? formatDate(worker.created_at) : 'Not available';
    
    if (worker.contract) {
        document.getElementById('viewContract').textContent = worker.contract.contract_number + ' - ' + worker.contract.title;
    } else {
        document.getElementById('viewContract').textContent = 'No contract assigned';
    }
    
    if (worker.added_by) {
        document.getElementById('viewAddedBy').textContent = worker.added_by.name || 'Unknown user';
    } else {
        document.getElementById('viewAddedBy').textContent = 'System';
    }
    
    var statusColors = {
        'active': 'success',
        'inactive': 'warning',
        'completed': 'success',
        'terminated': 'danger'
    };
    var statusIcons = {
        'active': 'fa-play-circle',
        'inactive': 'fa-pause-circle',
        'completed': 'fa-flag-checkered',
        'terminated': 'fa-times-circle'
    };
    var statusColor = statusColors[worker.status] || 'secondary';
    var statusIcon = statusIcons[worker.status] || 'fa-question-circle';
    var statusDisplay = ucfirst(worker.status || 'unknown');
    
    document.getElementById('viewStatus').innerHTML = 
        '<span class="status-badge status-' + statusColor + '">' +
            '<i class="fas ' + statusIcon + ' mr-1"></i> ' + statusDisplay +
        '</span>';
    document.getElementById('viewWorkerStatus').innerHTML = 
        '<span class="status-badge status-' + statusColor + '">' +
            '<i class="fas ' + statusIcon + ' mr-1"></i> ' + statusDisplay +
        '</span>';
    
    if (worker.trade) {
        document.getElementById('viewWorkerTrade').innerHTML = 
            '<span class="trade-badge">' +
                '<i class="fas fa-tools mr-1"></i> ' + ucfirst(worker.trade) +
            '</span>';
    } else {
        document.getElementById('viewWorkerTrade').textContent = 'No trade specified';
    }

    // ============================================ //
    // DISPLAY BADGE INFORMATION //
    // ============================================ //
    var badgeSection = document.getElementById('viewBadgeSection');
    if (worker.badge) {
        badgeSection.style.display = 'block';
        document.getElementById('viewBadgeNumber').textContent = worker.badge.badge_number || '-';
        document.getElementById('viewBadgeValidFrom').textContent = worker.badge.valid_from ? formatDate(worker.badge.valid_from) : 'Not set';
        document.getElementById('viewBadgeValidUntil').textContent = worker.badge.valid_until ? formatDate(worker.badge.valid_until) : 'Not set';
        document.getElementById('viewBadgeVerificationCount').textContent = worker.badge.verification_count || 0;
        document.getElementById('viewBadgeLastVerified').textContent = worker.badge.last_verified_at ? formatDate(worker.badge.last_verified_at) : 'Never';
        
        var badgeStatusColors = {
            'active': 'success',
            'expired': 'danger',
            'inactive': 'secondary'
        };
        var badgeStatusDisplay = ucfirst(worker.badge.status || 'unknown');
        var badgeColor = badgeStatusColors[worker.badge.status] || 'secondary';
        document.getElementById('viewBadgeStatus').innerHTML = 
            '<span class="status-badge status-' + badgeColor + '">' +
                '<i class="fas ' + (worker.badge.status === 'active' ? 'fa-check-circle' : 'fa-exclamation-circle') + ' mr-1"></i> ' + 
                badgeStatusDisplay +
            '</span>';

        var badgeStatusEl = document.getElementById('viewWorkerBadgeStatus');
        badgeStatusEl.style.display = 'inline-flex';
        badgeStatusEl.className = 'status-badge status-' + badgeColor;
        badgeStatusEl.innerHTML = '<i class="fas fa-id-card mr-1"></i> ' + badgeStatusDisplay;

        if (worker.badge.verification_url) {
            var urlEl = document.getElementById('viewBadgeVerificationUrl');
            var link = urlEl.querySelector('a');
            if (link) {
                link.href = worker.badge.verification_url;
            }
            document.getElementById('viewBadgeVerificationUrlRow').style.display = 'flex';
        } else {
            document.getElementById('viewBadgeVerificationUrlRow').style.display = 'none';
        }

        var sendBtn = document.getElementById('viewSendBadgeBtn');
        sendBtn.style.display = 'inline-flex';
        sendBtn.onclick = function() {
            if (worker.contract_id) {
                closeViewWorkerModal();
                if (worker.badge) {
                    sendBadge(worker.id, worker.contract_id, 'resend');
                } else {
                    sendBadge(worker.id, worker.contract_id, 'send');
                }
            }
        };
    } else {
        badgeSection.style.display = 'none';
        document.getElementById('viewWorkerBadgeStatus').style.display = 'none';
        document.getElementById('viewSendBadgeBtn').style.display = 'none';
    }
    
    // ============================================ //
// ✅ DISPLAY SITE ASSIGNMENT - FIXED //
// ============================================ //
var siteSection = document.getElementById('viewSiteAssignmentSection');
var siteToggleBtn = document.getElementById('viewToggleSiteBtn');
var siteUnassignBtn = document.getElementById('viewUnassignSiteBtn');

if (worker.contract_id && worker.status === 'active') {
    siteSection.style.display = 'block';
    
    // ✅ Check if worker is assigned to site
    var isAssigned = worker.is_assigned_to_site || false;
    
    // Update site status display
    var siteStatusHtml = isAssigned ? 
        '<span class="status-badge status-success"><i class="fas fa-check-circle mr-1"></i> Assigned to Site</span>' :
        '<span class="status-badge status-warning"><i class="fas fa-clock mr-1"></i> Not Assigned</span>';
    document.getElementById('viewSiteStatus').innerHTML = siteStatusHtml;
    
    document.getElementById('viewSiteLocation').textContent = worker.contract?.location || 'Site location not specified';
    document.getElementById('viewSiteAssignedOn').textContent = worker.assigned_to_site_at ? formatDate(worker.assigned_to_site_at) : 'Not yet assigned';
    
    // Show site status in header
    var siteStatusEl = document.getElementById('viewWorkerSiteStatus');
    siteStatusEl.style.display = 'inline-flex';
    siteStatusEl.className = 'status-badge status-' + (isAssigned ? 'success' : 'warning');
    siteStatusEl.innerHTML = '<i class="fas fa-hard-hat mr-1"></i> ' + (isAssigned ? 'On Site' : 'Not Assigned');
    
    // ✅ CORRECT: Show/hide buttons based on assignment status
    if (isAssigned) {
        // Worker IS assigned - show "Remove from Site" button only
        siteToggleBtn.style.display = 'none';
        
        siteUnassignBtn.style.display = 'inline-flex';
        siteUnassignBtn.innerHTML = '<i class="fas fa-times mr-2"></i> Remove from Site';
        siteUnassignBtn.className = 'btn btn-danger';
        siteUnassignBtn.onclick = function() {
            closeViewWorkerModal();
            toggleSiteAssignment(worker.id, worker.contract_id);
        };
    } else {
        // Worker is NOT assigned - show "Assign to Site" button only
        siteToggleBtn.style.display = 'inline-flex';
        siteToggleBtn.innerHTML = '<i class="fas fa-hard-hat mr-2"></i> Assign to Site';
        siteToggleBtn.className = 'btn btn-success';
        siteToggleBtn.onclick = function() {
            closeViewWorkerModal();
            toggleSiteAssignment(worker.id, worker.contract_id);
        };
        
        siteUnassignBtn.style.display = 'none';
    }
} else {
    siteSection.style.display = 'none';
    document.getElementById('viewWorkerSiteStatus').style.display = 'none';
    siteToggleBtn.style.display = 'none';
    siteUnassignBtn.style.display = 'none';
}
    
    // Skills
    var skillsContainer = document.getElementById('viewSkills');
    var skillsSection = document.getElementById('viewSkillsSection');
    if (worker.skills && Array.isArray(worker.skills) && worker.skills.length > 0) {
        skillsSection.style.display = 'block';
        skillsContainer.innerHTML = '';
        worker.skills.forEach(function(skill) {
            if (skill) {
                var skillBadge = document.createElement('span');
                skillBadge.className = 'skill-badge';
                skillBadge.textContent = ucfirst(skill);
                skillsContainer.appendChild(skillBadge);
            }
        });
    } else {
        skillsSection.style.display = 'none';
    }
    
    var descSection = document.getElementById('viewDescriptionSection');
    if (worker.service_description) {
        descSection.style.display = 'block';
        document.getElementById('viewServiceDescription').textContent = worker.service_description;
    } else {
        descSection.style.display = 'none';
    }
    
    var notesSection = document.getElementById('viewNotesSection');
    if (worker.notes) {
        notesSection.style.display = 'block';
        document.getElementById('viewNotes').textContent = worker.notes;
    } else {
        notesSection.style.display = 'none';
    }
    
    var editBtn = document.getElementById('viewEditWorkerBtn');
    if (worker.contract_id) {
        editBtn.style.display = 'inline-flex';
        editBtn.onclick = function() {
            closeViewWorkerModal();
            openEditWorkerModal(worker.id, worker.contract_id);
        };
    } else {
        editBtn.style.display = 'none';
    }
}

function showWorkerError(message) {
    document.getElementById('viewWorkerLoading').style.display = 'none';
    document.getElementById('viewWorkerContent').style.display = 'none';
    document.getElementById('viewWorkerError').style.display = 'block';
    document.getElementById('viewWorkerErrorMessage').textContent = message || 'Unable to load worker details.';
}

// ============================================ //
// EDIT WORKER MODAL FUNCTIONS //
// ============================================ //

function openEditWorkerModal(workerId, contractId) {
    var modal = document.getElementById('editWorkerModal');
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
    
    document.getElementById('editWorkerLoading').style.display = 'block';
    document.getElementById('editWorkerFormContainer').style.display = 'none';
    document.getElementById('editWorkerError').style.display = 'none';
    
    document.getElementById('edit_worker_id').value = workerId;
    document.getElementById('edit_contract_id').value = contractId;
    
    var form = document.getElementById('editWorkerForm');
    var actionUrl = "/contractor/contracts/" + contractId + "/workers/" + workerId;
    form.action = actionUrl;
    
    fetchEditWorkerDetails(workerId, contractId);
}

function closeEditWorkerModal() {
    document.getElementById('editWorkerModal').classList.remove('show');
    document.body.style.overflow = '';
}

function fetchEditWorkerDetails(workerId, contractId) {
    var url = "/contractor/contracts/" + contractId + "/workers/" + workerId;
    
    fetch(url, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        credentials: 'same-origin'
    })
    .then(function(response) {
        if (!response.ok) {
            return response.text().then(function(text) {
                throw new Error('Server returned ' + response.status + ': ' + text.substring(0, 100));
            });
        }
        return response.text();
    })
    .then(function(text) {
        // ✅ Remove BOM and other invisible characters
        var cleanedText = text.replace(/^\uFEFF/, '').replace(/^\u00BB\uBF/, '').replace(/^\uFFFE/, '');
        if (cleanedText.charCodeAt(0) === 65279) {
            cleanedText = cleanedText.substring(1);
        }
        
        try {
            var data = JSON.parse(cleanedText);
            if (data.success) {
                populateEditForm(data.worker);
            } else {
                showEditError(data.message || 'Failed to load worker details.');
            }
        } catch (parseError) {
            showEditError('Invalid response format from server.');
        }
    })
    .catch(function(error) {
        showEditError('An error occurred while loading worker details: ' + error.message);
    });
}

function populateEditForm(worker) {
    document.getElementById('editWorkerLoading').style.display = 'none';
    document.getElementById('editWorkerFormContainer').style.display = 'block';
    document.getElementById('editWorkerError').style.display = 'none';
    
    document.getElementById('edit_full_name').value = worker.full_name || '';
    document.getElementById('edit_job_title').value = worker.job_title || '';
    document.getElementById('edit_email').value = worker.email || '';
    document.getElementById('edit_phone').value = worker.phone || '';
    document.getElementById('edit_trade').value = worker.trade || '';
    document.getElementById('edit_specialization').value = worker.specialization || '';
    document.getElementById('edit_daily_rate').value = worker.daily_rate || '';
    document.getElementById('edit_contract_rate').value = worker.contract_rate || '';
    document.getElementById('edit_status').value = worker.status || 'active';
    document.getElementById('edit_id_number').value = worker.id_number || '';
    document.getElementById('edit_address').value = worker.address || '';
    document.getElementById('edit_start_date').value = worker.start_date ? worker.start_date.substring(0, 10) : '';
    document.getElementById('edit_end_date').value = worker.end_date ? worker.end_date.substring(0, 10) : '';
    document.getElementById('edit_service_description').value = worker.service_description || '';
    document.getElementById('edit_notes').value = worker.notes || '';
    
    // Site assignment
    document.getElementById('edit_assign_to_site').checked = worker.is_assigned_to_site || false;
    
    var skillsSelect = document.getElementById('edit_skills');
    if (worker.skills && Array.isArray(worker.skills)) {
        for (var i = 0; i < skillsSelect.options.length; i++) {
            var option = skillsSelect.options[i];
            option.selected = worker.skills.includes(option.value);
        }
    }
}

function showEditError(message) {
    document.getElementById('editWorkerLoading').style.display = 'none';
    document.getElementById('editWorkerFormContainer').style.display = 'none';
    document.getElementById('editWorkerError').style.display = 'block';
    document.getElementById('editWorkerErrorMessage').textContent = message || 'Unable to load worker details.';
}

function submitEditWorkerForm() {
    var fullName = document.getElementById('edit_full_name');
    if (!fullName.value.trim()) {
        fullName.focus();
        fullName.classList.add('is-invalid');
        alert('Please enter the worker\'s full name.');
        return;
    }
    fullName.classList.remove('is-invalid');
    
    document.getElementById('editWorkerForm').submit();
}

// ============================================ //
// BADGE FUNCTIONS //
// ============================================ //

function sendBadge(workerId, contractId, action) {
    var url = "/contractor/contracts/" + contractId + "/workers/" + workerId;
    
    if (action === 'resend') {
        url += '/resend-badge';
    } else {
        url += '/send-badge';
    }
    
    var confirmMsg = action === 'resend' ? 
        'Are you sure you want to resend the badge to this worker?' : 
        'Are you sure you want to send a badge to this worker?';
    
    if (!confirm(confirmMsg)) {
        return;
    }
    
    var button = document.querySelector('[onclick*="sendBadge(' + workerId + '"]');
    var originalHtml = button ? button.innerHTML : '';
    if (button) {
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Sending...';
        button.disabled = true;
    }
    
    showNotification('Sending badge...', 'info');
    
    fetch(url, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        credentials: 'same-origin'
    })
    .then(function(response) {
        if (!response.ok) {
            return response.text().then(function(text) {
                // ✅ Remove BOM and other invisible characters
                var cleanText = text.replace(/^\uFEFF/, '').replace(/^\u00BB\uBF/, '').replace(/^\uFFFE/, '');
                if (cleanText.charCodeAt(0) === 65279) {
                    cleanText = cleanText.substring(1);
                }
                try {
                    var errorData = JSON.parse(cleanText);
                    throw new Error(errorData.message || 'Server returned ' + response.status);
                } catch (e) {
                    throw new Error('Server returned ' + response.status + ': ' + cleanText.substring(0, 100));
                }
            });
        }
        return response.text();
    })
    .then(function(text) {
        // ✅ Remove BOM and other invisible characters
        var cleanText = text.replace(/^\uFEFF/, '').replace(/^\u00BB\uBF/, '').replace(/^\uFFFE/, '');
        if (cleanText.charCodeAt(0) === 65279) {
            cleanText = cleanText.substring(1);
        }
        
        var data;
        try {
            data = JSON.parse(cleanText);
        } catch (parseError) {
            var cleaned = cleanText.replace(/[^\x20-\x7E]/g, '').trim();
            try {
                data = JSON.parse(cleaned);
            } catch (e) {
                throw new Error('Invalid response format from server.');
            }
        }
        
        if (button) {
            button.innerHTML = originalHtml;
            button.disabled = false;
        }
        
        if (data.success) {
            showNotification(data.message || 'Badge sent successfully!', 'success');
            setTimeout(function() {
                location.reload();
            }, 1500);
        } else {
            showNotification(data.message || 'Failed to send badge. Please try again.', 'error');
        }
    })
    .catch(function(error) {
        if (button) {
            button.innerHTML = originalHtml;
            button.disabled = false;
        }
        
        var errorMessage = error.message || 'An unexpected error occurred.';
        console.error('Badge send error:', error);
        showNotification('Failed to send badge: ' + errorMessage, 'error');
    });
}

function showNotification(message, type) {
    var existing = document.querySelector('.custom-notification');
    if (existing) {
        existing.remove();
    }
    
    var notification = document.createElement('div');
    notification.className = 'custom-notification notification-' + (type || 'info');
    notification.innerHTML = message;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        border-radius: 8px;
        font-weight: 500;
        z-index: 99999;
        max-width: 450px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideInRight 0.3s ease;
        font-size: 14px;
        color: #ffffff;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
    `;
    
    var colors = {
        'success': '#16a34a',
        'error': '#dc2626',
        'warning': '#f59e0b',
        'info': '#2563eb'
    };
    notification.style.backgroundColor = colors[type] || colors.info;
    
    document.body.appendChild(notification);
    
    setTimeout(function() {
        if (notification.parentNode) {
            notification.style.opacity = '0';
            notification.style.transition = 'opacity 0.3s ease';
            setTimeout(function() {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }
    }, 5000);
}

var notificationStyle = document.createElement('style');
notificationStyle.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(notificationStyle);

// ============================================ //
// UTILITY FUNCTIONS //
// ============================================ //

function formatDate(dateString) {
    if (!dateString) return 'Not set';
    var date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function ucfirst(string) {
    if (!string) return '';
    return string.charAt(0).toUpperCase() + string.slice(1);
}

// ============================================ //
// ADD WORKER MODAL FUNCTIONS //
// ============================================ //

function openAddWorkerModal() {
    document.getElementById('addWorkerModal').classList.add('show');
    document.body.style.overflow = 'hidden';
    goBackToContracts();
    document.getElementById('workerForm').reset();
    document.getElementById('send_badge').checked = true;
    document.getElementById('assign_to_site').checked = true;
    document.querySelectorAll('.is-invalid').forEach(function(el) {
        el.classList.remove('is-invalid');
    });
    document.querySelectorAll('.contract-item').forEach(function(item) {
        item.classList.remove('selected');
    });
    var searchInput = document.getElementById('modalContractSearch');
    if (searchInput) {
        searchInput.value = '';
        filterContracts();
    }
}

function closeAddWorkerModal() {
    document.getElementById('addWorkerModal').classList.remove('show');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        var addModal = document.getElementById('addWorkerModal');
        if (addModal.classList.contains('show')) {
            closeAddWorkerModal();
        }
        var viewModal = document.getElementById('viewWorkerModal');
        if (viewModal.classList.contains('show')) {
            closeViewWorkerModal();
        }
        var editModal = document.getElementById('editWorkerModal');
        if (editModal.classList.contains('show')) {
            closeEditWorkerModal();
        }
    }
});

// ============================================ //
// CONTRACT SELECTION FUNCTIONS //
// ============================================ //

function filterContracts() {
    var searchTerm = document.getElementById('modalContractSearch').value.toLowerCase().trim();
    var contractItems = document.querySelectorAll('#modalContractList .contract-item');
    
    contractItems.forEach(function(item) {
        var searchData = item.getAttribute('data-search') || '';
        if (searchData.includes(searchTerm) || searchTerm === '') {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

function selectContract(btn) {
    var contractId = btn.getAttribute('data-contract-id');
    var contractNumber = btn.getAttribute('data-contract-number');
    
    var contractItem = btn.closest('.contract-item');
    if (!contractItem) {
        alert('Invalid contract selection.');
        return;
    }
    
    document.getElementById('contract_id').value = contractId;
    document.getElementById('selectedContractNumber').textContent = contractNumber;
    
    var form = document.getElementById('workerForm');
    var actionUrl = "/contractor/contracts/" + contractId + "/workers";
    form.action = actionUrl;
    
    document.getElementById('stepContractSelect').style.display = 'none';
    document.getElementById('stepWorkerForm').style.display = 'block';
    document.getElementById('submitWorkerBtn').style.display = 'inline-flex';
    document.getElementById('backToContractsBtn').style.display = 'inline-flex';
    
    document.querySelectorAll('.contract-item').forEach(function(item) {
        item.classList.remove('selected');
        var itemBtn = item.querySelector('.select-contract-btn');
        if (itemBtn && itemBtn.getAttribute('data-contract-id') == contractId) {
            item.classList.add('selected');
        }
    });
}

function goBackToContracts() {
    document.getElementById('stepWorkerForm').style.display = 'none';
    document.getElementById('stepContractSelect').style.display = 'block';
    document.getElementById('submitWorkerBtn').style.display = 'none';
    document.getElementById('backToContractsBtn').style.display = 'none';
    document.getElementById('contract_id').value = '';
}

function submitWorkerForm() {
    var contractId = document.getElementById('contract_id').value;
    if (!contractId) {
        alert('Please select a contract first.');
        return;
    }
    
    var fullName = document.getElementById('full_name');
    if (!fullName.value.trim()) {
        fullName.focus();
        fullName.classList.add('is-invalid');
        alert('Please enter the worker\'s full name.');
        return;
    }
    fullName.classList.remove('is-invalid');
    
    if (!/^\d+$/.test(contractId)) {
        alert('Invalid contract selection.');
        return;
    }
    
    document.getElementById('workerForm').submit();
}

// ============================================ //
// END DATE VALIDATION //
// ============================================ //

document.addEventListener('DOMContentLoaded', function() {
    var startDate = document.getElementById('start_date');
    var endDate = document.getElementById('end_date');
    
    if (startDate && endDate) {
        startDate.addEventListener('change', function() {
            if (endDate.value && this.value && endDate.value < this.value) {
                endDate.value = '';
            }
            endDate.min = this.value;
        });
    }
    
    var editStartDate = document.getElementById('edit_start_date');
    var editEndDate = document.getElementById('edit_end_date');
    
    if (editStartDate && editEndDate) {
        editStartDate.addEventListener('change', function() {
            if (editEndDate.value && this.value && editEndDate.value < this.value) {
                editEndDate.value = '';
            }
            editEndDate.min = this.value;
        });
    }
    
    document.querySelectorAll('.contract-item').forEach(function(item) {
        item.addEventListener('click', function(e) {
            if (e.target.closest('.select-contract-btn')) return;
            var btn = this.querySelector('.select-contract-btn');
            if (btn) {
                selectContract(btn);
            }
        });
    });
});

// ============================================ //
// ADDITIONAL STYLES //
// ============================================ //

var style = document.createElement('style');
style.textContent = `
    .site-assignment-toggle {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        align-items: flex-start;
    }
    
    .site-toggle-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.3rem 0.75rem;
        border-radius: 6px;
        border: none;
        font-size: 0.75rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .site-toggle-btn.assigned {
        background-color: rgba(var(--success-rgb), 0.1);
        color: var(--success);
        border: 1px solid rgba(var(--success-rgb), 0.3);
    }
    
    .site-toggle-btn.assigned:hover {
        background-color: rgba(var(--danger-rgb), 0.1);
        color: var(--danger);
        border-color: rgba(var(--danger-rgb), 0.3);
    }
    
    .site-toggle-btn.unassigned {
        background-color: rgba(var(--primary-rgb), 0.1);
        color: var(--primary);
        border: 1px solid rgba(var(--primary-rgb), 0.3);
    }
    
    .site-toggle-btn.unassigned:hover {
        background-color: rgba(var(--primary-rgb), 0.2);
    }
    
    .site-toggle-btn .status-label {
        font-size: 0.7rem;
    }
    
    .site-toggle-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .site-info {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.65rem;
        color: var(--text-secondary);
    }
    
    .site-info i {
        font-size: 0.6rem;
    }
    
    .unassign-btn {
        background: none;
        border: none;
        color: var(--danger);
        cursor: pointer;
        font-size: 0.8rem;
        padding: 0;
        margin-left: 0.25rem;
        transition: color 0.2s ease;
    }
    
    .unassign-btn:hover {
        color: #b91c1c;
    }
    
    .badge-info {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        align-items: flex-start;
    }
    .badge-number {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-primary);
        font-family: monospace;
        background-color: var(--bg-secondary);
        padding: 0.1rem 0.4rem;
        border-radius: 4px;
    }
    .badge-expiry {
        font-size: 0.65rem;
        color: var(--text-muted);
    }
    .action-badge-send {
        background-color: rgba(var(--info-rgb), 0.1);
        color: var(--info);
    }
    .action-badge-send:hover {
        background-color: rgba(var(--info-rgb), 0.2);
    }
    .action-badge-resend {
        background-color: rgba(var(--success-rgb), 0.1);
        color: var(--success);
    }
    .action-badge-resend:hover {
        background-color: rgba(var(--success-rgb), 0.2);
    }
`;
document.head.appendChild(style);

console.log('🚀 Contractor Worker Management Loaded');
</script>
@endpush

<style>
/* ============================================ */
/* CUSTOM MODAL CSS - Theme Aware */
/* ============================================ */
.custom-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    overflow-y: auto;
    padding: 1rem;
}

.custom-modal.show {
    display: flex !important;
    align-items: center;
    justify-content: center;
}

.custom-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1;
}

.custom-modal-dialog {
    position: relative;
    z-index: 2;
    max-width: 800px;
    width: 100%;
    margin: auto;
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Theme-aware modal content */
.custom-modal-content.theme-aware {
    background-color: var(--card-bg, #ffffff);
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    border: 1px solid var(--border-color, #e5e7eb);
}

.custom-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--border-color, #e5e7eb);
    background-color: var(--bg-secondary, #f3f4f6);
}

.custom-modal-title {
    font-size: 1.125rem;
    font-weight: 600;
    margin: 0;
    color: var(--text-primary, #1f2937);
}

.custom-modal-close {
    background: none;
    border: none;
    font-size: 1.75rem;
    line-height: 1;
    color: var(--text-secondary, #6b7280);
    cursor: pointer;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    transition: background-color 0.2s;
}

.custom-modal-close:hover {
    background-color: rgba(0, 0, 0, 0.05);
    color: var(--text-primary, #1f2937);
}

.custom-modal-body {
    padding: 1.5rem;
    max-height: 70vh;
    overflow-y: auto;
    background-color: var(--card-bg, #ffffff);
}

.custom-modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color, #e5e7eb);
    gap: 0.5rem;
    background-color: var(--bg-secondary, #f3f4f6);
}

/* ============================================ */
/* WORKER PROFILE STYLES */
/* ============================================ */
.worker-profile {
    padding: 0.5rem 0;
}

.worker-profile-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-color, #e5e7eb);
    margin-bottom: 1.5rem;
}

.worker-avatar {
    width: 4rem;
    height: 4rem;
    border-radius: 50%;
    background-color: rgba(var(--primary-rgb, 37, 99, 235), 0.1);
    color: var(--primary, #2563eb);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    flex-shrink: 0;
}

.worker-name-display {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary, #1f2937);
    margin: 0 0 0.25rem 0;
}

.worker-header-meta {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    align-items: center;
}

.worker-details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.detail-section {
    background-color: var(--bg-secondary, #f3f4f6);
    border-radius: 8px;
    padding: 1rem;
    border: 1px solid var(--border-color, #e5e7eb);
}

.section-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary, #1f2937);
    margin: 0 0 0.75rem 0;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border-color, #e5e7eb);
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.375rem 0;
    border-bottom: 1px solid rgba(var(--border-color, #e5e7eb), 0.3);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-size: 0.813rem;
    color: var(--text-secondary, #6b7280);
    font-weight: 500;
}

.detail-value {
    font-size: 0.813rem;
    color: var(--text-primary, #1f2937);
    text-align: right;
    max-width: 60%;
    word-break: break-word;
}

.detail-text {
    color: var(--text-primary, #1f2937);
    font-size: 0.875rem;
    margin: 0;
    line-height: 1.6;
    white-space: pre-wrap;
}

.skills-container {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.skill-badge {
    background-color: rgba(var(--primary-rgb, 37, 99, 235), 0.1);
    color: var(--primary, #2563eb);
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
}

/* Dark theme overrides for worker details */
[data-theme="dark"] .detail-section {
    background-color: var(--bg-secondary, #2a2a3c);
    border-color: var(--border-color, #39394a);
}

[data-theme="dark"] .detail-row {
    border-bottom-color: rgba(var(--border-color, #39394a), 0.3);
}

[data-theme="dark"] .section-title {
    border-bottom-color: var(--border-color, #39394a);
}

/* ============================================ */
/* SPINNER */
/* ============================================ */
.spinner-border {
    display: inline-block;
    width: 2rem;
    height: 2rem;
    border: 0.25em solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: spinner-border 0.75s linear infinite;
    color: var(--primary, #2563eb);
}

@keyframes spinner-border {
    to { transform: rotate(360deg); }
}

.visually-hidden {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    border: 0 !important;
}

/* ============================================ */
/* RESPONSIVE OVERRIDES */
/* ============================================ */
@media (max-width: 768px) {
    .worker-details-grid {
        grid-template-columns: 1fr;
    }
    
    .worker-profile-header {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    .worker-header-meta {
        justify-content: center;
    }
    
    .detail-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.125rem;
    }
    
    .detail-value {
        text-align: left;
        max-width: 100%;
    }
}

@media (max-width: 480px) {
    .custom-modal-dialog {
        margin: 0.5rem;
    }
    
    .custom-modal-body {
        max-height: 60vh;
    }
    
    .worker-avatar {
        width: 3rem;
        height: 3rem;
        font-size: 1.75rem;
    }
    
    .worker-name-display {
        font-size: 1rem;
    }
}

/* ============================================ */
/* CSS VARIABLES (Theme fallbacks) */
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

/* Dark theme overrides for modal */
[data-theme="dark"] .custom-modal-content.theme-aware {
    background-color: var(--card-bg, #2a2a3c);
    border-color: var(--border-color, #39394a);
}

[data-theme="dark"] .custom-modal-header {
    background-color: var(--bg-secondary, #2a2a3c);
    border-bottom-color: var(--border-color, #39394a);
}

[data-theme="dark"] .custom-modal-body {
    background-color: var(--card-bg, #2a2a3c);
}

[data-theme="dark"] .custom-modal-footer {
    background-color: var(--bg-secondary, #2a2a3c);
    border-top-color: var(--border-color, #39394a);
}

[data-theme="dark"] .custom-modal-title {
    color: var(--text-primary, #e4e4e4);
}

[data-theme="dark"] .custom-modal-close {
    color: var(--text-secondary, #a0a0a0);
}

[data-theme="dark"] .custom-modal-close:hover {
    background-color: rgba(255, 255, 255, 0.05);
    color: var(--text-primary, #e4e4e4);
}

[data-theme="dark"] .detail-section {
    background-color: var(--bg-secondary, #2a2a3c);
    border-color: var(--border-color, #39394a);
}

[data-theme="dark"] .detail-row {
    border-bottom-color: rgba(var(--border-color, #39394a), 0.3);
}

[data-theme="dark"] .section-title {
    border-bottom-color: var(--border-color, #39394a);
}

[data-theme="dark"] .detail-label {
    color: var(--text-secondary, #a0a0a0);
}

[data-theme="dark"] .detail-value {
    color: var(--text-primary, #e4e4e4);
}

[data-theme="dark"] .detail-text {
    color: var(--text-primary, #e4e4e4);
}

[data-theme="dark"] .skill-badge {
    background-color: rgba(var(--primary-rgb, 140, 130, 255), 0.2);
    color: var(--primary, #8c82ff);
}

[data-theme="dark"] .worker-avatar {
    background-color: rgba(var(--primary-rgb, 140, 130, 255), 0.2);
    color: var(--primary, #8c82ff);
}

[data-theme="dark"] .worker-name-display {
    color: var(--text-primary, #e4e4e4);
}

/* ============================================ */
/* BASE LAYOUT */
/* ============================================ */
.workers-dashboard {
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
    gap: 1rem;
}

.page-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.page-title .fa-users {
    color: var(--primary);
}

.page-subtitle {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

.header-actions {
    display: flex;
    gap: 0.5rem;
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

.form-control.is-invalid {
    border-color: var(--danger);
}

.form-control.is-invalid:focus {
    box-shadow: 0 0 0 3px rgba(var(--danger-rgb), 0.1);
}

.form-label {
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
    font-size: 0.875rem;
}

.text-danger {
    color: var(--danger);
}

.form-text {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

.input-group {
    display: flex;
    align-items: center;
}

.input-group-text {
    padding: 0.625rem 0.875rem;
    background-color: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-right: none;
    border-radius: 8px 0 0 8px;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.input-group .form-control {
    border-radius: 0 8px 8px 0;
}

.row {
    display: flex;
    flex-wrap: wrap;
    margin: -0.5rem;
}

.col-md-4, .col-md-6 {
    flex: 0 0 auto;
    padding: 0.5rem;
    width: 100%;
}

@media (min-width: 768px) {
    .col-md-4 {
        width: 33.333333%;
    }
    .col-md-6 {
        width: 50%;
    }
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

.btn-info {
    background-color: var(--info);
    color: #ffffff;
    border-color: var(--info);
}

.btn-info:hover {
    background-color: #0891b2;
    border-color: #0891b2;
    transform: translateY(-1px);
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.813rem;
}

.btn-link {
    background: none;
    border: none;
    color: var(--primary);
    padding: 0;
    font-weight: 500;
}

.btn-link:hover {
    text-decoration: underline;
    color: #1d4ed8;
}

.float-end {
    float: right;
}

/* ============================================ */
/* STEP INDICATOR */
/* ============================================ */
.step-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    padding: 0.75rem 1rem;
    background-color: var(--bg-secondary);
    border-radius: 8px;
    flex-wrap: wrap;
}

.step-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    background-color: var(--border-color);
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 0.813rem;
    transition: all 0.3s ease;
}

.step-badge.active {
    background-color: var(--primary);
    color: #ffffff;
}

.step-badge.completed {
    background-color: var(--success);
    color: #ffffff;
}

.step-label {
    font-size: 0.813rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.step-label.completed-label {
    color: var(--success);
}

.step-divider {
    color: var(--text-muted);
}

/* ============================================ */
/* CONTRACT SELECTION */
/* ============================================ */
.contract-selection-section {
    padding: 0.5rem 0;
}

.alert {
    padding: 0.75rem 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    border: 1px solid transparent;
}

.alert-info {
    background-color: rgba(var(--info-rgb), 0.1);
    border-color: rgba(var(--info-rgb), 0.2);
    color: var(--info);
}

.alert-success {
    background-color: rgba(var(--success-rgb), 0.1);
    border-color: rgba(var(--success-rgb), 0.2);
    color: var(--success);
}

.contract-search-box {
    margin-bottom: 1rem;
}

.contract-list-container {
    max-height: 350px;
    overflow-y: auto;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 0.5rem;
}

.contract-item {
    display: block;
    padding: 0.75rem 1rem;
    border: 2px solid transparent;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    cursor: pointer;
    transition: all 0.2s ease;
    background-color: var(--card-bg);
}

.contract-item:last-child {
    margin-bottom: 0;
}

.contract-item:hover {
    border-color: rgba(var(--primary-rgb), 0.2);
    background-color: rgba(var(--primary-rgb), 0.02);
}

.contract-item.selected {
    border-color: var(--primary);
    background-color: rgba(var(--primary-rgb), 0.05);
}

.contract-item-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.contract-item-left {
    flex: 1;
    min-width: 0;
}

.contract-item-number {
    font-weight: 600;
    color: var(--primary);
    font-size: 0.938rem;
}

.contract-item-number i {
    margin-right: 0.5rem;
}

.contract-item-title {
    font-size: 0.875rem;
    color: var(--text-primary);
    margin-top: 0.125rem;
}

.contract-item-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.25rem;
}

.meta-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.meta-tag i {
    font-size: 0.688rem;
}

.status-tag-approved {
    color: var(--success);
}

.status-tag-in_progress {
    color: var(--primary);
}

.status-tag-pending {
    color: var(--warning);
}

.status-tag-draft {
    color: var(--text-muted);
}

.contract-item-right {
    flex-shrink: 0;
}

/* ============================================ */
/* SELECTED CONTRACT SUMMARY */
/* ============================================ */
.selected-contract-summary {
    margin-bottom: 1.5rem;
}

/* ============================================ */
/* EMPTY CONTRACTS */
/* ============================================ */
.empty-contracts {
    text-align: center;
    padding: 2rem 1rem;
}

.empty-contracts i {
    display: block;
}

/* ============================================ */
/* TABLE */
/* ============================================ */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.workers-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.workers-table thead {
    border-bottom: 1px solid var(--border-color);
}

.workers-table th {
    text-align: left;
    padding: 0.75rem 1rem;
    font-weight: 500;
    color: var(--text-secondary);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.workers-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
}

.workers-table tbody tr {
    transition: background-color 0.15s ease;
}

.workers-table tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.02);
}

/* Table Content Styles */
.worker-info {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.worker-name {
    font-weight: 500;
    color: var(--text-primary);
    font-size: 0.938rem;
}

.worker-contact {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.contact-item {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.contact-item i {
    font-size: 0.688rem;
}

.contract-info {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.contract-number .contract-link {
    font-weight: 500;
    color: var(--primary);
    text-decoration: none;
    font-size: 0.875rem;
}

.contract-number .contract-link:hover {
    text-decoration: underline;
}

.contract-title {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.trade-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
}

.job-title {
    font-size: 0.875rem;
    color: var(--text-primary);
}

.rate-amount {
    font-weight: 600;
    color: var(--text-primary);
}

.rate-period {
    font-weight: 400;
    font-size: 0.75rem;
    color: var(--text-secondary);
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
    background: none;
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

.action-edit {
    background-color: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
}

.action-edit:hover {
    background-color: rgba(var(--primary-rgb), 0.2);
}

.action-contract {
    background-color: rgba(var(--secondary-rgb), 0.1);
    color: var(--secondary);
}

.action-contract:hover {
    background-color: rgba(var(--secondary-rgb), 0.2);
}

.action-badge-send {
    background-color: rgba(var(--info-rgb), 0.1);
    color: var(--info);
}

.action-badge-send:hover {
    background-color: rgba(var(--info-rgb), 0.2);
}

.action-badge-resend {
    background-color: rgba(var(--success-rgb), 0.1);
    color: var(--success);
}

.action-badge-resend:hover {
    background-color: rgba(var(--success-rgb), 0.2);
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

.text-muted {
    color: var(--text-muted);
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
    
    .workers-table {
        font-size: 0.813rem;
    }
    
    .workers-table th,
    .workers-table td {
        padding: 0.5rem;
    }
    
    .workers-table td:last-child,
    .workers-table th:last-child {
        padding-right: 0.5rem;
    }
    
    .workers-table th:nth-child(3),
    .workers-table td:nth-child(3),
    .workers-table th:nth-child(4),
    .workers-table td:nth-child(4) {
        display: none;
    }
    
    .custom-modal-dialog {
        margin: 0.5rem;
    }
    
    .custom-modal-body {
        max-height: 60vh;
    }
    
    .contract-item-content {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .contract-item-right {
        width: 100%;
    }
    
    .contract-item-right .btn {
        width: 100%;
        justify-content: center;
    }
    
    .custom-modal-footer {
        flex-wrap: wrap;
    }
    
    .custom-modal-footer .btn {
        flex: 1;
        min-width: 120px;
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
    
    .step-indicator {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .step-label {
        font-size: 0.75rem;
    }
}
</style>
@endsection