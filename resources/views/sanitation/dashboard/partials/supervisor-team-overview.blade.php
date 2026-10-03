{{-- resources/views/sanitation/dashboard/partials/supervisor-team-overview.blade.php --}}

{{--
    Supervisor Team Overview
    ------------------------
    Expected variables:
      - $supervisor   : App\Models\SanitationPersonnel (the current supervisor)
      - $teamOverview : array  { is_root, subordinate_count, workers, drivers, active_jobs, pending_approvals }

    Requires the following CSS classes from the main dashboard blade:
      .perf-tile, .request-item, .empty-state
      .btn-outline, .btn-sm
      .icon-btn
--}}

@php
    $isRoot            = $teamOverview['is_root'] ?? false;
    $subordinateCount  = $teamOverview['subordinate_count'] ?? 0;
    $activeJobs        = $teamOverview['active_jobs'] ?? 0;
    $pendingApprovals  = $teamOverview['pending_approvals'] ?? 0;

    $workers = $teamOverview['workers'] ?? collect();
    $drivers = $teamOverview['drivers'] ?? collect();

    // Combine for a single sorted list (workers first, then drivers)
    $teamMembers = $workers->merge($drivers);
@endphp

<div class="card p-6 mb-6" id="supervisor-team-overview">

    {{-- ============================================ --}}
    {{-- Header                                       --}}
    {{-- ============================================ --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
        <h3 class="font-semibold" style="color: var(--text-primary);">
            <i class="fas fa-users-cog mr-2" style="color: var(--primary);"></i>
            Your Team

            @if($isRoot)
                <span class="ml-2 text-xs px-2 py-0.5 rounded-full"
                      style="background: rgba(var(--warning-rgb), 0.15); color: var(--warning);">
                    <i class="fas fa-crown mr-1"></i>Root Supervisor
                </span>
            @endif
        </h3>

        <a href="{{ route('sanitation.personnel.index') }}"
           class="text-sm hover:underline"
           style="color: var(--primary);">
            Manage <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>

    {{-- ============================================ --}}
    {{-- Summary tiles                                --}}
    {{-- ============================================ --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
        <div class="perf-tile">
            <div class="text-2xl font-bold" style="color: var(--primary);">
                {{ $subordinateCount }}
            </div>
            <div class="text-xs" style="color: var(--text-secondary);">
                {{ $isRoot ? 'Team Members' : 'Direct Reports' }}
            </div>
        </div>

        <div class="perf-tile">
            <div class="text-2xl font-bold" style="color: var(--info);">
                {{ $workers->count() }}
            </div>
            <div class="text-xs" style="color: var(--text-secondary);">Workers</div>
        </div>

        <div class="perf-tile">
            <div class="text-2xl font-bold" style="color: var(--warning);">
                {{ $drivers->count() }}
            </div>
            <div class="text-xs" style="color: var(--text-secondary);">Drivers</div>
        </div>

        <div class="perf-tile">
            <div class="text-2xl font-bold" style="color: var(--success);">
                {{ $activeJobs }}
            </div>
            <div class="text-xs" style="color: var(--text-secondary);">Active Jobs</div>
        </div>
    </div>

    {{-- ============================================ --}}
    {{-- Pending approvals alert (if any)             --}}
    {{-- ============================================ --}}
    @if($pendingApprovals > 0)
        <div class="rounded p-3 mb-4"
             style="background: rgba(var(--warning-rgb), 0.1); border-left: 4px solid var(--warning);">
            <div class="flex items-start gap-3">
                <i class="fas fa-clock text-warning text-lg"></i>
                <div>
                    <div class="font-medium text-sm" style="color: var(--text-primary);">
                        {{ $pendingApprovals }} request(s) awaiting landlord approval
                    </div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        <a href="{{ route('sanitation.approvals.pending') }}"
                           class="hover:underline"
                           style="color: var(--primary);">
                            View pending approvals <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================ --}}
    {{-- Team member list                             --}}
    {{-- ============================================ --}}
    @if($teamMembers->count() > 0)
        <div class="space-y-2">
            @foreach($teamMembers as $member)
                @php
                    $memberRole = $member->role ?? 'worker';
                    $roleColour = match ($memberRole) {
                        'driver'     => 'var(--warning)',
                        'supervisor' => 'var(--primary)',
                        default      => 'var(--info)',
                    };
                @endphp

                <div class="request-item">
                    <div class="min-w-0 flex-1">
                        <div class="font-medium truncate flex items-center gap-2 flex-wrap"
                             style="color: var(--text-primary);">
                            {{ $member->full_name }}

                            @if($member->status === 'active')
                                <span class="inline-flex items-center gap-1 text-xs"
                                      style="color: var(--success);">
                                    <span class="status-dot status-dot--success" style="margin-right: 0;"></span>
                                    Active
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs"
                                      style="color: var(--text-secondary);">
                                    <span class="status-dot" style="background-color: #94a3b8; margin-right: 0;"></span>
                                    {{ ucfirst($member->status) }}
                                </span>
                            @endif
                        </div>

                        <div class="text-xs mt-1 flex items-center gap-2 flex-wrap"
                             style="color: var(--text-secondary);">
                            <span style="color: {{ $roleColour }}; font-weight: 500;">
                                {{ ucfirst($memberRole) }}
                            </span>

                            @if($member->employee_id)
                                <span class="mx-1">•</span>
                                {{ $member->employee_id }}
                            @endif

                            @if($member->phone)
                                <span class="mx-1">•</span>
                                {{ $member->phone }}
                            @endif
                        </div>
                    </div>

                    <a href="{{ route('sanitation.personnel.show', $member) }}"
                       class="icon-btn"
                       title="View {{ $member->full_name }}">
                        <i class="fas fa-eye"></i>
                    </a>
                </div>
            @endforeach
        </div>
    @else
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <p>No team members assigned yet</p>

            @if($isRoot)
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    Assign workers and drivers to this supervisor to see them here.
                </p>
            @else
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    Your direct reports will appear here once assigned.
                </p>
            @endif
        </div>
    @endif

    {{-- ============================================ --}}
    {{-- Action bar                                   --}}
    {{-- ============================================ --}}
    <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t" style="border-color: var(--border-color);">
        <a href="{{ route('sanitation.personnel.index') }}" class="btn-outline btn-sm">
            <i class="fas fa-users mr-1"></i> All Personnel
        </a>

        <a href="{{ route('sanitation.requests.pending') }}" class="btn-outline btn-sm">
            <i class="fas fa-clock mr-1"></i> Pending Requests
        </a>

        @if($isRoot)
            <a href="{{ route('sanitation.approvals.pending') }}" class="btn-outline btn-sm">
                <i class="fas fa-check-double mr-1"></i> Pending Approvals
            </a>
        @endif
    </div>
</div>