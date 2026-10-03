{{-- resources/views/sanitation/dashboard/partials/worker-daily-tasks.blade.php --}}

{{--
    Worker Daily Tasks
    ------------------
    Expected variables:
      - $worker        : App\Models\SanitationPersonnel (the current worker)
      - $personnelStats: array  { total_requests, completed, pending, completion_rate, total_weight, ... }

    Requires the following CSS classes from the main dashboard blade:
      .perf-tile, .request-item, .empty-state
      .btn-primary, .btn-outline, .btn-sm
      .status-dot
--}}

@php
    $pending     = (int) ($personnelStats['pending'] ?? 0);
    $active      = (int) ($personnelStats['active'] ?? 0);
    $completedToday = (int) ($personnelStats['completed_today'] ?? 0);
    $totalWeight = (float) ($personnelStats['total_weight'] ?? 0);
@endphp

<div class="card p-6 mb-6" id="worker-daily-tasks">

    {{-- ============================================ --}}
    {{-- Header                                       --}}
    {{-- ============================================ --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-4">
        <h3 class="font-semibold" style="color: var(--text-primary);">
            <i class="fas fa-clipboard-check mr-2" style="color: var(--primary);"></i>
            Today's Tasks
        </h3>

        <a href="{{ route('sanitation.requests.pending') }}"
           class="text-sm hover:underline"
           style="color: var(--primary);">
            View Pending <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>

    {{-- ============================================ --}}
    {{-- Summary tiles                                --}}
    {{-- ============================================ --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <div class="perf-tile">
            <div class="text-2xl font-bold" style="color: var(--warning);">
                {{ $pending }}
            </div>
            <div class="text-xs" style="color: var(--text-secondary);">Pending</div>
        </div>

        <div class="perf-tile">
            <div class="text-2xl font-bold" style="color: var(--primary);">
                {{ $active }}
            </div>
            <div class="text-xs" style="color: var(--text-secondary);">Active</div>
        </div>

        <div class="perf-tile">
            <div class="text-2xl font-bold" style="color: var(--success);">
                {{ $completedToday }}
            </div>
            <div class="text-xs" style="color: var(--text-secondary);">Done Today</div>
        </div>

        <div class="perf-tile">
            <div class="text-2xl font-bold" style="color: var(--info);">
                {{ number_format($totalWeight, 1) }}
            </div>
            <div class="text-xs" style="color: var(--text-secondary);">Total kg</div>
        </div>
    </div>

    {{-- ============================================ --}}
    {{-- Focus banner: pending tasks                  --}}
    {{-- ============================================ --}}
    @if($pending > 0)
        <div class="rounded p-4 mb-4"
             style="background: rgba(var(--warning-rgb), 0.1); border-left: 4px solid var(--warning);">
            <div class="flex items-start gap-3">
                <i class="fas fa-clock text-warning text-xl"></i>
                <div class="flex-1">
                    <div class="font-medium" style="color: var(--text-primary);">
                        You have {{ $pending }} pending task{{ $pending === 1 ? '' : 's' }}
                    </div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        Open the pending list to accept and complete them.
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('sanitation.requests.pending') }}" class="btn-primary btn-sm">
                            <i class="fas fa-arrow-right mr-1"></i> View Pending Tasks
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================ --}}
    {{-- Focus banner: active tasks                   --}}
    {{-- ============================================ --}}
    @if($active > 0)
        <div class="rounded p-4 mb-4"
             style="background: rgba(var(--primary-rgb), 0.08); border-left: 4px solid var(--primary);">
            <div class="flex items-start gap-3">
                <i class="fas fa-spinner text-primary text-xl"></i>
                <div class="flex-1">
                    <div class="font-medium" style="color: var(--text-primary);">
                        You have {{ $active }} active task{{ $active === 1 ? '' : 's' }} in progress
                    </div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        Update their status as you move through each stop.
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('sanitation.requests.index') }}" class="btn-outline btn-sm">
                            <i class="fas fa-list mr-1"></i> My Requests
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================ --}}
    {{-- All clear                                    --}}
    {{-- ============================================ --}}
    @if($pending === 0 && $active === 0)
        <div class="empty-state">
            <i class="fas fa-check-circle" style="color: var(--success); opacity: 0.6;"></i>
            <p style="color: var(--text-primary); font-weight: 500;">All caught up!</p>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">
                No pending or active tasks right now.
                @if($completedToday > 0)
                    You completed {{ $completedToday }} task{{ $completedToday === 1 ? '' : 's' }} today.
                @endif
            </p>
        </div>
    @endif

    {{-- ============================================ --}}
    {{-- Action bar                                   --}}
    {{-- ============================================ --}}
    <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t" style="border-color: var(--border-color);">
        <a href="{{ route('sanitation.requests.index') }}" class="btn-outline btn-sm">
            <i class="fas fa-list mr-1"></i> All My Requests
        </a>

        <a href="{{ route('sanitation.profile.edit') }}" class="btn-outline btn-sm">
            <i class="fas fa-user-edit mr-1"></i> Update Availability
        </a>
    </div>
</div>