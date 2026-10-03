{{-- resources/views/sanitation/dashboard.blade.php --}}

@extends('layouts.san')

@section('title', 'Sanitation Dashboard')

@section('content')
<div class="sanitation-dashboard">
    <div class="max-w-7xl mx-auto">

        {{-- ============================================ --}}
        {{-- 👋 WELCOME / STATUS BAR                     --}}
        {{-- ============================================ --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-trash-alt mr-2" style="color: var(--primary);"></i>
                    Sanitation Dashboard
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Welcome back, {{ Auth::user()->name }}! Manage waste collection and sanitation services.
                </p>
            </div>

            <div class="flex items-center space-x-3 flex-wrap gap-2">
                {{-- ✅ Role badge --}}
                @if($personnel)
                    <div class="status-pill"
                         style="background: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                        <i class="fas fa-user-tag mr-2"></i>
                        {{ ucfirst($role ?? $personnel->role) }}

                        @if(($role ?? $personnel->role) === 'driver' && $personnel->vehicle_number)
                            <span class="mx-2" style="color: var(--text-secondary);">•</span>
                            <i class="fas fa-truck mr-1"></i>{{ $personnel->vehicle_number }}
                        @endif
                    </div>
                @endif

                @if($personnel && $personnel->is_available)
                    <div class="status-pill status-pill--success">
                        <i class="fas fa-circle text-xs mr-2 animate-pulse"></i>
                        Available
                    </div>
                @elseif($personnel)
                    <div class="status-pill status-pill--danger">
                        <i class="fas fa-circle text-xs mr-2"></i>
                        Unavailable
                    </div>
                @endif

                <a href="{{ route('sanitation.profile.edit') }}" class="btn-outline btn-sm">
                    <i class="fas fa-user-edit mr-2"></i> Edit Profile
                </a>
                <a href="{{ route('sanitation.requests.pending') }}" class="btn-primary btn-sm">
                    <i class="fas fa-clock mr-2"></i> Pending Requests
                </a>
            </div>
        </div>

        {{-- ============================================ --}}
        {{-- 📊 QUICK STATS                              --}}
        {{-- ============================================ --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="card p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold" style="color: var(--text-primary);">
                            {{ $quickStats['active_jobs'] ?? 0 }}
                        </div>
                        <div class="text-xs" style="color: var(--text-secondary);">Active Jobs</div>
                    </div>
                    <div class="stat-icon" style="background: rgba(var(--primary-rgb), 0.1);">
                        <i class="fas fa-spinner text-xl" style="color: var(--primary);"></i>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold" style="color: var(--success);">
                            {{ $quickStats['completed_today'] ?? 0 }}
                        </div>
                        <div class="text-xs" style="color: var(--text-secondary);">Completed Today</div>
                    </div>
                    <div class="stat-icon" style="background: rgba(var(--success-rgb), 0.1);">
                        <i class="fas fa-check-circle text-xl" style="color: var(--success);"></i>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold" style="color: var(--warning);">
                            {{ $quickStats['pending_requests'] ?? 0 }}
                        </div>
                        <div class="text-xs" style="color: var(--text-secondary);">Pending Requests</div>
                    </div>
                    <div class="stat-icon" style="background: rgba(var(--warning-rgb), 0.1);">
                        <i class="fas fa-clock text-xl" style="color: var(--warning);"></i>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold" style="color: var(--info);">
                            {{ $systemStats['total_properties'] ?? 0 }}
                        </div>
                        <div class="text-xs" style="color: var(--text-secondary);">Total Properties</div>
                    </div>
                    <div class="stat-icon" style="background: rgba(var(--info-rgb), 0.1);">
                        <i class="fas fa-building text-xl" style="color: var(--info);"></i>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================ --}}
        {{-- 📈 SYSTEM STATISTICS                        --}}
        {{-- ============================================ --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="card p-6">
                <h3 class="font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i>
                    System Overview
                </h3>
                <div class="space-y-3">
                    <div class="stat-row">
                        <span class="text-sm" style="color: var(--text-secondary);">Total Requests</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $systemStats['total_requests'] ?? 0 }}</span>
                    </div>
                    <div class="stat-row">
                        <span class="text-sm" style="color: var(--text-secondary);">Active Requests</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $systemStats['active_requests'] ?? 0 }}</span>
                    </div>
                    <div class="stat-row">
                        <span class="text-sm" style="color: var(--text-secondary);">Pending Requests</span>
                        <span class="text-sm font-medium" style="color: var(--warning);">{{ $systemStats['pending_requests'] ?? 0 }}</span>
                    </div>
                    <div class="stat-row stat-row--last">
                        <span class="text-sm" style="color: var(--text-secondary);">Completed Today</span>
                        <span class="text-sm font-medium" style="color: var(--success);">{{ $systemStats['completed_today'] ?? 0 }}</span>
                    </div>
                </div>
            </div>

            <div class="card p-6">
                <h3 class="font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-users mr-2" style="color: var(--info);"></i>
                    Personnel
                </h3>
                <div class="space-y-3">
                    <div class="stat-row">
                        <span class="text-sm" style="color: var(--text-secondary);">Total Personnel</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $systemStats['total_personnel'] ?? 0 }}</span>
                    </div>
                    <div class="stat-row">
                        <span class="text-sm" style="color: var(--text-secondary);">Active Personnel</span>
                        <span class="text-sm font-medium" style="color: var(--success);">{{ $systemStats['active_personnel'] ?? 0 }}</span>
                    </div>
                    <div class="stat-row">
                        <span class="text-sm" style="color: var(--text-secondary);">Total Workers</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $systemStats['total_workers'] ?? 0 }}</span>
                    </div>
                    <div class="stat-row stat-row--last">
                        <span class="text-sm" style="color: var(--text-secondary);">Active Workers</span>
                        <span class="text-sm font-medium" style="color: var(--success);">{{ $systemStats['active_workers'] ?? 0 }}</span>
                    </div>
                </div>
            </div>

            <div class="card p-6">
                <h3 class="font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-weight mr-2" style="color: var(--warning);"></i>
                    Waste Collection
                </h3>
                <div class="space-y-3">
                    <div class="stat-row">
                        <span class="text-sm" style="color: var(--text-secondary);">Linked Properties</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $systemStats['linked_properties'] ?? 0 }}</span>
                    </div>
                    <div class="stat-row">
                        <span class="text-sm" style="color: var(--text-secondary);">Available Properties</span>
                        <span class="text-sm font-medium" style="color: var(--info);">{{ $systemStats['available_properties'] ?? 0 }}</span>
                    </div>
                    <div class="stat-row stat-row--last">
                        <span class="text-sm" style="color: var(--text-secondary);">Total Weight Today</span>
                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ number_format($systemStats['total_weight_today'] ?? 0, 2) }} kg</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============================================ --}}
        {{-- 🏆 PERSONNEL PERFORMANCE                    --}}
        {{-- ============================================ --}}
        @if($personnel)
            <div class="card p-6 mb-6">
                <h3 class="font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-user mr-2" style="color: var(--primary);"></i>
                    Your Performance
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="perf-tile">
                        <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ $personnelStats['total_requests'] ?? 0 }}</div>
                        <div class="text-xs" style="color: var(--text-secondary);">Total Requests</div>
                    </div>
                    <div class="perf-tile">
                        <div class="text-2xl font-bold" style="color: var(--success);">{{ $personnelStats['completed'] ?? 0 }}</div>
                        <div class="text-xs" style="color: var(--text-secondary);">Completed</div>
                    </div>
                    <div class="perf-tile">
                        <div class="text-2xl font-bold" style="color: var(--warning);">{{ $personnelStats['pending'] ?? 0 }}</div>
                        <div class="text-xs" style="color: var(--text-secondary);">Pending</div>
                    </div>
                    <div class="perf-tile">
                        <div class="text-2xl font-bold" style="color: var(--info);">{{ $personnelStats['completion_rate'] ?? 0 }}%</div>
                        <div class="text-xs" style="color: var(--text-secondary);">Completion Rate</div>
                    </div>
                    <div class="perf-tile">
                        <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($personnelStats['total_weight'] ?? 0, 2) }}</div>
                        <div class="text-xs" style="color: var(--text-secondary);">Total Waste (kg)</div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ============================================ --}}
        {{-- 🎯 ROLE-SPECIFIC WORKSPACE                  --}}
        {{-- ============================================ --}}
        {{-- Each partial expects the variables below.    --}}
        {{-- If the controller fell back to null, we      --}}
        {{-- still include with safe defaults.            --}}

        @if($isDriver)
            @include('sanitation.dashboard.partials.driver-route-planner', [
                'driver'        => $personnel,
                'driverRoute'   => $driverRoute ?? [
                    'stops'        => collect(),
                    'stop_count'   => 0,
                    'total_weight' => 0,
                    'zone'         => null,
                    'map_center'   => ['lat' => null, 'lng' => null],
                    'generated_at' => null,
                ],
                'driverVehicle' => $driverVehicle ?? [
                    'number' => null,
                    'type'   => null,
                    'zone'   => null,
                ],
            ])
        @elseif($isSupervisor)
            @include('sanitation.dashboard.partials.supervisor-team-overview', [
                'supervisor'   => $personnel,
                'teamOverview' => $teamOverview ?? [
                    'is_root'           => false,
                    'subordinate_count' => 0,
                    'workers'           => collect(),
                    'drivers'           => collect(),
                    'active_jobs'       => 0,
                    'pending_approvals' => 0,
                ],
            ])
        @elseif($isWorker)
            @include('sanitation.dashboard.partials.worker-daily-tasks', [
                'worker'         => $personnel,
                'personnelStats' => $personnelStats ?? [],
            ])
        @else
            {{-- Admin / super-admin / no-role viewers --}}
            <div class="card p-6 mb-6">
                <div class="empty-state">
                    <i class="fas fa-user-shield"></i>
                    <p>You're viewing as an administrator.</p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        Route planners and team views appear for drivers, supervisors, and workers.
                    </p>
                </div>
            </div>
        @endif

        {{-- ============================================ --}}
        {{-- 📋 RECENT REQUESTS + WEEKLY TRENDS          --}}
        {{-- ============================================ --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Recent Requests --}}
            <div class="card p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold" style="color: var(--text-primary);">
                        <i class="fas fa-list mr-2" style="color: var(--primary);"></i>
                        Recent Requests
                    </h3>
                    <a href="{{ route('sanitation.requests.index') }}" class="text-sm hover:underline" style="color: var(--primary);">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>

                @if(isset($recentRequests) && $recentRequests->count() > 0)
                    <div class="space-y-3">
                        @foreach($recentRequests as $request)
                            <div class="request-item">
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium truncate"
                                         title="{{ $request->property->property_name ?? 'Unknown Property' }}"
                                         style="color: var(--text-primary);">
                                        {{ $request->property->property_name ?? 'Unknown Property' }}
                                    </div>
                                    <div class="text-xs mt-1 flex items-center" style="color: var(--text-secondary);">
                                        <span class="status-dot
                                            @if($request->status == 'completed') status-dot--success
                                            @elseif($request->status == 'pending') status-dot--warning
                                            @elseif($request->status == 'cancelled') status-dot--danger
                                            @else status-dot--info @endif"></span>
                                        {{ ucfirst($request->status) }}
                                        <span class="mx-2">•</span>
                                        {{ $request->created_at->diffForHumans() }}
                                    </div>
                                </div>
                                <span class="priority-badge priority-badge--{{ $request->priority }}">
                                    {{ ucfirst($request->priority) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No recent collection requests</p>
                    </div>
                @endif
            </div>

            {{-- Weekly Trends --}}
            <div class="card p-6">
                <h3 class="font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-chart-line mr-2" style="color: var(--info);"></i>
                    Weekly Trends
                </h3>

                @if(isset($weeklyTrends) && count($weeklyTrends) > 0)
                    @php
                        $trends      = collect($weeklyTrends);
                        $maxRequests = $trends->max('requests') ?: 1;
                        $totalReq    = $trends->sum('requests');
                        $totalDone   = $trends->sum('completed');
                    @endphp
                    <div class="space-y-2">
                        @foreach($trends as $trend)
                            <div class="flex items-center">
                                <div class="w-12 text-sm font-medium" style="color: var(--text-secondary);">
                                    {{ $trend['day'] }}
                                </div>
                                <div class="flex-1 mx-2">
                                    <div class="h-2 rounded-full overflow-hidden" style="background-color: var(--border-color);">
                                        <div class="h-full rounded-full transition-all duration-500"
                                             style="width: {{ ($trend['requests'] / $maxRequests) * 100 }}%;
                                                    background: linear-gradient(to right, var(--primary), var(--success));"></div>
                                    </div>
                                </div>
                                <div class="text-sm font-medium" style="color: var(--text-primary);">
                                    {{ $trend['requests'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="grid grid-cols-2 gap-4 mt-4 pt-4 border-t" style="border-color: var(--border-color);">
                        <div class="text-center">
                            <div class="text-sm" style="color: var(--text-secondary);">Total Requests</div>
                            <div class="text-xl font-bold" style="color: var(--text-primary);">
                                {{ $totalReq }}
                            </div>
                        </div>
                        <div class="text-center">
                            <div class="text-sm" style="color: var(--text-secondary);">Total Completed</div>
                            <div class="text-xl font-bold" style="color: var(--success);">
                                {{ $totalDone }}
                            </div>
                        </div>
                    </div>
                @else
                    <div class="empty-state">
                        <i class="fas fa-chart-bar"></i>
                        <p>No trend data available</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- ============================================ --}}
        {{-- 🏅 TOP PERFORMERS                           --}}
        {{-- ============================================ --}}
        @if(isset($topPerformers) && count($topPerformers) > 0)
            <div class="card p-6 mt-6">
                <h3 class="font-semibold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-trophy mr-2" style="color: var(--warning);"></i>
                    Top Performers
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($topPerformers as $index => $performer)
                        <div class="performer-card">
                            <div class="performer-rank
                                @if($index == 0) performer-rank--gold
                                @elseif($index == 1) performer-rank--silver
                                @else performer-rank--bronze @endif">
                                {{ $index + 1 }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-medium truncate" style="color: var(--text-primary);">
                                    {{ $performer['name'] }}
                                </div>
                                <div class="text-xs" style="color: var(--text-secondary);">
                                    {{ $performer['completed'] }} completed • {{ $performer['completion_rate'] }}% rate
                                </div>
                            </div>
                            <div class="text-sm font-bold whitespace-nowrap" style="color: var(--primary);">
                                {{ $performer['total'] }} total
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>
</div>
@endsection

{{-- ============================================ --}}
{{-- 🎨 STYLES                                    --}}
{{-- ============================================ --}}
@push('styles')
<style>
    /* ============================================ */
    /* 🎯 SANITATION DASHBOARD — Scoped Styles      */
    /* ============================================ */

    .sanitation-dashboard {
        padding: 0;
    }

    /* Status Pills */
    .status-pill {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        border-radius: 999px;
        font-size: 0.875rem;
        font-weight: 500;
    }
    .status-pill--success {
        background-color: rgba(var(--success-rgb), 0.1);
        color: var(--success);
        border: 1px solid rgba(var(--success-rgb), 0.3);
    }
    .status-pill--danger {
        background-color: rgba(var(--danger-rgb), 0.1);
        color: var(--danger);
        border: 1px solid rgba(var(--danger-rgb), 0.3);
    }

    /* Cards */
    .sanitation-dashboard .card {
        background-color: var(--card-bg);
        border-radius: 0.75rem;
        border: 1px solid var(--border-color);
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .sanitation-dashboard .card:hover {
        border-color: var(--primary);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08), 0 2px 4px -1px rgba(0, 0, 0, 0.04);
    }

    /* Stat Icons */
    .stat-icon {
        width: 3rem;
        height: 3rem;
        border-radius: 999px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    /* Stat Rows */
    .stat-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--border-color);
    }
    .stat-row--last {
        padding-bottom: 0;
        border-bottom: none;
    }

    /* Performance Tiles */
    .perf-tile {
        text-align: center;
        padding: 0.75rem;
        border-radius: 0.5rem;
        background-color: var(--bg-secondary);
    }

    /* Request Items */
    .request-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem;
        border-radius: 0.5rem;
        background-color: var(--bg-secondary);
        gap: 0.75rem;
    }

    .status-dot {
        display: inline-block;
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 999px;
        margin-right: 0.5rem;
    }
    .status-dot--success { background-color: var(--success, #22c55e); }
    .status-dot--warning { background-color: var(--warning, #eab308); }
    .status-dot--danger  { background-color: var(--danger,  #ef4444); }
    .status-dot--info    { background-color: var(--info,    #3b82f6); }

    /* Priority Badges */
    .priority-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        font-weight: 500;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .priority-badge--emergency { background-color: rgba(239, 68, 68, 0.15);  color: #ef4444; }
    .priority-badge--high      { background-color: rgba(249, 115, 22, 0.15); color: #f97316; }
    .priority-badge--medium    { background-color: rgba(234, 179, 8, 0.15);  color: #ca8a04; }
    .priority-badge--low       { background-color: rgba(59, 130, 246, 0.15); color: #3b82f6; }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 2rem 1rem;
        color: var(--text-secondary);
    }
    .empty-state i {
        font-size: 2.5rem;
        display: block;
        margin-bottom: 0.75rem;
        opacity: 0.3;
    }

    /* Performer Cards */
    .performer-card {
        display: flex;
        align-items: center;
        padding: 0.75rem;
        border-radius: 0.5rem;
        background-color: var(--bg-secondary);
        gap: 0.75rem;
    }
    .performer-rank {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 999px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-weight: 700;
        font-size: 0.875rem;
        flex-shrink: 0;
    }
    .performer-rank--gold   { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); }
    .performer-rank--silver { background: linear-gradient(135deg, #22d3ee 0%, #3b82f6 100%); }
    .performer-rank--bronze { background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%); }

    /* Buttons */
    .btn-primary,
    .btn-outline {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-weight: 500;
        font-size: 0.875rem;
        border: 1px solid transparent;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }
    .btn-primary {
        background: linear-gradient(to right, var(--primary), var(--info));
        color: #fff;
    }
    .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }

    .btn-outline {
        background-color: transparent;
        color: var(--primary);
        border-color: var(--primary);
    }
    .btn-outline:hover { background-color: rgba(var(--primary-rgb), 0.1); }

    .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.75rem; }

    /* ============================================ */
    /* 🚚 DRIVER ROUTE PLANNER                      */
    /* (used by the included partial)               */
    /* ============================================ */

    .route-chip {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 500;
        background-color: rgba(var(--primary-rgb), 0.1);
        color: var(--primary);
    }
    .route-chip--muted {
        background-color: var(--bg-secondary);
        color: var(--text-secondary);
    }

    .route-stop {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem;
        border-radius: 0.5rem;
        background-color: var(--bg-secondary);
        border: 1px solid transparent;
        transition: border-color 0.2s;
    }
    .route-stop:hover { border-color: var(--primary); }

    .route-stop__index {
        width: 2rem;
        height: 2rem;
        border-radius: 999px;
        background: linear-gradient(135deg, var(--primary), var(--info));
        color: #fff;
        font-weight: 700;
        font-size: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .route-stop__body { flex: 1; min-width: 0; }
    .route-stop__actions {
        display: flex;
        gap: 0.5rem;
        flex-shrink: 0;
    }

    .icon-btn {
        width: 2rem;
        height: 2rem;
        border-radius: 0.375rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background-color: var(--bg-secondary);
        color: var(--primary);
        border: 1px solid var(--border-color);
        transition: all 0.2s;
        cursor: pointer;
        text-decoration: none;
    }
    .icon-btn:hover {
        background-color: rgba(var(--primary-rgb), 0.1);
        border-color: var(--primary);
    }
    .icon-btn--success { color: var(--success); }
    .icon-btn--success:hover {
        background-color: rgba(var(--success-rgb), 0.1);
        border-color: var(--success);
    }

    /* Pulse animation */
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50%      { opacity: 0.5; }
    }
    .animate-pulse {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    /* Dark mode badge overrides */
    [data-theme="dark"] .priority-badge--emergency { background-color: rgba(248, 113, 113, 0.2); color: #f87171; }
    [data-theme="dark"] .priority-badge--high      { background-color: rgba(251, 146, 60, 0.2);  color: #fb923c; }
    [data-theme="dark"] .priority-badge--medium    { background-color: rgba(251, 191, 36, 0.2);  color: #fbbf24; }
    [data-theme="dark"] .priority-badge--low       { background-color: rgba(96, 165, 250, 0.2);  color: #60a5fa; }

    /* Responsive tweak */
    @media (max-width: 640px) {
        .route-stop {
            flex-wrap: wrap;
        }
        .route-stop__actions {
            width: 100%;
            justify-content: flex-end;
        }
    }
</style>
@endpush

{{-- ============================================ --}}
{{-- 🧩 SCRIPTS                                   --}}
{{-- ============================================ --}}
{{-- The driver-route-planner partial does        --}}
{{-- @push('scripts') @once ... @endonce.         --}}
{{-- This stub guarantees the 'scripts' stack     --}}
{{-- exists in the layout, even if this file is  --}}
{{-- the only thing rendering on the page.        --}}
@push('scripts')
@endpush