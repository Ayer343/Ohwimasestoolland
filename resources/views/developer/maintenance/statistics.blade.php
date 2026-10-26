@extends('layouts.dev')

@section('title', 'Maintenance Statistics')

@php
    use App\Models\Maintenance;

    $total              = (int) ($statistics['total'] ?? 0);
    $completedCount     = (int) ($statistics['completed'] ?? 0);
    $completionRate     = (float) ($statistics['completion_rate'] ?? 0);
    $avgDuration        = (float) ($statistics['avg_duration'] ?? 0);
    $avgDurationVariance= (float) ($statistics['avg_duration_variance'] ?? 0);

    $statusColors = [
        Maintenance::STATUS_DRAFT       => 'var(--info)',
        Maintenance::STATUS_SCHEDULED   => 'var(--primary)',
        Maintenance::STATUS_IN_PROGRESS => 'var(--warning)',
        Maintenance::STATUS_COMPLETED   => 'var(--success)',
        Maintenance::STATUS_CANCELLED   => 'var(--danger)',
    ];
    $statusLabels = [
        Maintenance::STATUS_DRAFT       => 'Draft',
        Maintenance::STATUS_SCHEDULED   => 'Scheduled',
        Maintenance::STATUS_IN_PROGRESS => 'In Progress',
        Maintenance::STATUS_COMPLETED   => 'Completed',
        Maintenance::STATUS_CANCELLED   => 'Cancelled',
    ];

    $impactColors = [
        'low'      => 'var(--success)',
        'medium'   => 'var(--warning)',
        'high'     => 'var(--warning)',
        'critical' => 'var(--danger)',
    ];

    $typeColors = [
        'planned'   => 'var(--primary)',
        'emergency' => 'var(--danger)',
        'hotfix'    => 'var(--warning)',
        'upgrade'   => 'var(--info)',
        'security'  => 'var(--success)',
    ];
    $typeLabels = [
        'planned'   => 'Planned Maintenance',
        'emergency' => 'Emergency Maintenance',
        'hotfix'    => 'Hotfix',
        'upgrade'   => 'System Upgrade',
        'security'  => 'Security Patch',
    ];

    // SVG circle constants
    $svgTrack = 'M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831';
@endphp

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- ============================================================
         | HEADER
         * ============================================================ --}}
    <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="flex justify-between items-center p-6 flex-wrap gap-3">
            <div>
                <h2 class="text-xl font-semibold" style="color: var(--text-primary);">
                    <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i> Maintenance Statistics
                </h2>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Comprehensive analytics and insights for maintenance management
                </p>
            </div>
            <div class="flex space-x-2 flex-wrap">
                <a href="{{ route('developer.maintenance.index') }}"
                   class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                   style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to List
                </a>
            </div>
        </div>
    </div>

    {{-- ============================================================
         | OVERVIEW CARDS
         * ============================================================ --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="card p-4" style="background-color: rgba(var(--primary-rgb), 0.1); border: 1px solid rgba(var(--primary-rgb), 0.2); border-radius: 0.75rem; color: var(--primary);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Total Maintenance</div>
                    <div class="text-2xl font-semibold">{{ number_format($total) }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">All time records</div>
                </div>
                <i class="fas fa-server text-2xl opacity-70"></i>
            </div>
        </div>

        <div class="card p-4" style="background-color: rgba(var(--success-rgb), 0.1); border: 1px solid rgba(var(--success-rgb), 0.2); border-radius: 0.75rem; color: var(--success);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Completed</div>
                    <div class="text-2xl font-semibold">{{ number_format($completedCount) }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        {{ round($completionRate, 1) }}% success rate
                    </div>
                </div>
                <i class="fas fa-check-circle text-2xl opacity-70"></i>
            </div>
        </div>

        <div class="card p-4" style="background-color: rgba(var(--info-rgb), 0.1); border: 1px solid rgba(var(--info-rgb), 0.2); border-radius: 0.75rem; color: var(--info);">
            <div class="flex justify-between items-center">
                <div>
                    <div class="text-sm" style="color: var(--text-secondary);">Avg Duration</div>
                    <div class="text-2xl font-semibold">{{ round($avgDuration) }} min</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        {{ round($avgDurationVariance, 1) }}% variance
                    </div>
                </div>
                <i class="fas fa-clock text-2xl opacity-70"></i>
            </div>
        </div>
    </div>

    {{-- ============================================================
         | TRENDS
         * ============================================================ --}}
    <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i> Trends &amp; Patterns
            </h3>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Historical data and performance metrics
            </p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                {{-- Monthly --}}
                <div>
                    <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        <i class="fas fa-calendar-alt mr-2"></i> Monthly Trends
                    </h4>
                    <div class="space-y-3">
                        @if(!empty($trends['monthly']))
                            @php $monthlyMax = max(1, (int) ($trends['monthly_max'] ?? 1)); @endphp
                            @foreach($trends['monthly'] as $month => $count)
                                <div class="flex items-center justify-between">
                                    <span class="text-sm" style="color: var(--text-primary);">{{ $month }}</span>
                                    <div class="flex items-center">
                                        <div class="w-32 h-2 rounded-full mr-2" style="background-color: var(--border-color);">
                                            <div class="h-full rounded-full"
                                                 style="background-color: var(--primary); width: {{ min(100, ($count / $monthlyMax) * 100) }}%;"></div>
                                        </div>
                                        <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $count }}</span>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="text-center py-4" style="color: var(--text-secondary);">
                                <i class="fas fa-chart-bar text-2xl mb-2 opacity-50"></i>
                                <p class="text-sm">No monthly data available</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Status --}}
                <div>
                    <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        <i class="fas fa-tasks mr-2"></i> Status Distribution
                    </h4>
                    <div class="space-y-3">
                        @forelse($statusDistribution ?? [] as $status => $count)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 rounded-full mr-2" style="background-color: {{ $statusColors[$status] ?? 'var(--text-secondary)' }};"></div>
                                    <span class="text-sm" style="color: var(--text-primary);">{{ $statusLabels[$status] ?? ucfirst($status) }}</span>
                                </div>
                                <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $count }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-center py-4" style="color: var(--text-secondary);">No status data</p>
                        @endforelse
                    </div>
                </div>

                {{-- Impact --}}
                <div>
                    <h4 class="text-sm font-medium mb-3" style="color: var(--text-secondary);">
                        <i class="fas fa-exclamation-triangle mr-2"></i> Impact Distribution
                    </h4>
                    <div class="space-y-3">
                        @forelse($impactDistribution ?? [] as $impact => $count)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 rounded-full mr-2" style="background-color: {{ $impactColors[$impact] ?? 'var(--text-secondary)' }};"></div>
                                    <span class="text-sm capitalize" style="color: var(--text-primary);">{{ $impact }}</span>
                                </div>
                                <span class="text-sm font-medium" style="color: var(--text-primary);">{{ $count }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-center py-4" style="color: var(--text-secondary);">No impact data</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================
         | PERFORMANCE METRICS (SVG rings)
         * ============================================================ --}}
    <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-tachometer-alt mr-2" style="color: var(--primary);"></i> Performance Metrics
            </h3>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Key performance indicators and efficiency measurements
            </p>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                @php
                    $rings = [
                        ['label' => 'Duration Accuracy',    'value' => (float) ($statistics['duration_accuracy'] ?? 0),    'color' => 'var(--success)', 'hint' => 'Actual vs Estimated'],
                        ['label' => 'On-time Completion',   'value' => (float) ($statistics['on_time_rate'] ?? 0),         'color' => 'var(--warning)', 'hint' => 'Within schedule'],
                        ['label' => 'Impact Accuracy',      'value' => (float) ($statistics['user_impact_accuracy'] ?? 0),'color' => 'var(--info)',    'hint' => 'Estimated vs Actual users'],
                        ['label' => 'Downtime Efficiency',  'value' => (float) ($statistics['downtime_efficiency'] ?? 0),  'color' => 'var(--primary)', 'hint' => 'Minimal disruption'],
                    ];
                @endphp

                @foreach($rings as $ring)
                    <div class="text-center stat-ring-wrap">
                        <div class="relative w-24 h-24 mx-auto mb-3">
                            <svg class="w-full h-full" viewBox="0 0 36 36" aria-hidden="true">
                                <path d="{{ $svgTrack }}" fill="none" stroke="var(--border-color)" stroke-width="3"/>
                                <path d="{{ $svgTrack }}"
                                      fill="none"
                                      stroke="{{ $ring['color'] }}"
                                      stroke-width="3"
                                      stroke-dasharray="{{ min(100, max(0, $ring['value'])) }}, 100"
                                      stroke-linecap="round"/>
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-lg font-bold" style="color: var(--text-primary);">
                                    {{ round($ring['value']) }}%
                                </span>
                            </div>
                        </div>
                        <h4 class="text-sm font-medium mb-1" style="color: var(--text-primary);">{{ $ring['label'] }}</h4>
                        <p class="text-xs" style="color: var(--text-secondary);">{{ $ring['hint'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ============================================================
         | TYPE DISTRIBUTION
         * ============================================================ --}}
    <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i> Maintenance Type Distribution
            </h3>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Breakdown of maintenance by type
            </p>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                @forelse($typeDistribution ?? [] as $type => $count)
                    <div class="text-center stat-ring-wrap">
                        <div class="relative w-20 h-20 mx-auto mb-2">
                            <svg class="w-full h-full" viewBox="0 0 36 36" aria-hidden="true">
                                <circle cx="18" cy="18" r="15.9155" fill="none"
                                        stroke="{{ $typeColors[$type] ?? 'var(--text-secondary)' }}"
                                        stroke-width="3"/>
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-lg font-bold" style="color: var(--text-primary);">{{ $count }}</span>
                            </div>
                        </div>
                        <h4 class="text-sm font-medium mb-1" style="color: var(--text-primary);">
                            {{ $typeLabels[$type] ?? ucfirst($type) }}
                        </h4>
                        @if($total > 0)
                            <p class="text-xs" style="color: var(--text-secondary);">
                                {{ round(($count / $total) * 100) }}% of total
                            </p>
                        @endif
                    </div>
                @empty
                    <p class="col-span-full text-center py-6 text-sm" style="color: var(--text-secondary);">
                        No type distribution data available
                    </p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ============================================================
         | RECENT MAINTENANCE
         * ============================================================ --}}
    <div class="card" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-history mr-2" style="color: var(--primary);"></i> Recent Maintenance
            </h3>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Latest maintenance activities
            </p>
        </div>

        <div class="p-6">
            <div class="space-y-3">
                @forelse($recentMaintenances ?? [] as $maintenance)
                    @php
                        $statusStyle = match ($maintenance->status) {
                            Maintenance::STATUS_COMPLETED   => ['success', 'var(--success)'],
                            Maintenance::STATUS_IN_PROGRESS => ['warning', 'var(--warning)'],
                            Maintenance::STATUS_CANCELLED   => ['danger',  'var(--danger)'],
                            default                         => ['info',    'var(--info)'],
                        };
                    @endphp
                    <div class="flex items-center justify-between p-3 rounded-lg border flex-wrap gap-2"
                         style="border-color: var(--border-color);">
                        <div class="flex items-center min-w-0 flex-1">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                                 style="background-color: rgba(var(--{{ $statusStyle[0] }}-rgb), 0.1); color: {{ $statusStyle[1] }};">
                                <i class="fas fa-tools"></i>
                            </div>
                            <div class="min-w-0">
                                <h4 class="font-medium text-sm truncate" style="color: var(--text-primary);">
                                    {{ Str::limit($maintenance->title, 50) }}
                                </h4>
                                <div class="flex items-center mt-1 flex-wrap gap-2">
                                    <span class="badge" style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                                        {{ $maintenance->reference_id }}
                                    </span>
                                    <span class="text-xs" style="color: var(--text-secondary);">
                                        {{ $maintenance->created_at->diffForHumans() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <a href="{{ route('developer.maintenance.show', $maintenance->id) }}"
                           class="p-2 rounded hover:bg-opacity-20 transition-colors flex-shrink-0"
                           style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);"
                           aria-label="View maintenance {{ $maintenance->reference_id }}">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                @empty
                    <div class="text-center py-8" style="color: var(--text-secondary);">
                        <i class="fas fa-tools text-2xl mb-2 opacity-50"></i>
                        <p class="text-sm">No recent maintenance found</p>
                    </div>
                @endforelse
            </div>

            @if(($recentMaintenances ?? collect())->count() > 0)
                <div class="mt-4 text-center">
                    <a href="{{ route('developer.maintenance.index') }}"
                       class="px-4 py-2 rounded-lg text-sm inline-flex items-center transition-all hover:opacity-80"
                       style="background-color: var(--primary); color: white;">
                        <i class="fas fa-list mr-2"></i> View All Maintenance
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- ============================================================
         | EXPORT / PRINT ACTIONS
         * ============================================================ --}}
    <div class="card no-print" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 0.75rem;">
        <div class="p-6">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div>
                    <h4 class="font-medium mb-2" style="color: var(--text-primary);">Export &amp; Share</h4>
                    <p class="text-sm" style="color: var(--text-secondary);">
                        Download or print these statistics.
                    </p>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle mr-1"></i>
                        Server-side PDF/Excel export endpoints are not enabled yet — use Print or Save-as-PDF from your browser.
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" onclick="MaintenanceStats.print()"
                            class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                            style="background-color: var(--primary); color: white;">
                        <i class="fas fa-print mr-2"></i> Print
                    </button>
                    <button type="button" onclick="MaintenanceStats.print()"
                            class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                            style="background-color: var(--danger); color: white;">
                        <i class="fas fa-file-pdf mr-2"></i> Save as PDF
                    </button>
                    <a href="{{ route('developer.maintenance.index') }}"
                       class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80 flex items-center"
                       style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                        <i class="fas fa-list mr-2"></i> All Maintenance
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.2rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 500;
        letter-spacing: 0.4px;
    }

    .card {
        transition: box-shadow 0.2s ease;
    }
    .card:hover {
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
    }

    /* Stat ring hover */
    .stat-ring-wrap .relative {
        transition: transform 0.25s ease;
    }
    .stat-ring-wrap:hover .relative {
        transform: scale(1.05);
    }

    /* Print-friendly styles */
    @media print {
        .no-print,
        button,
        a[href]:not([href^="#"]) {
            display: none !important;
        }

        .card {
            break-inside: avoid;
            page-break-inside: avoid;
            border: 1px solid #ddd !important;
            box-shadow: none !important;
            margin-bottom: 1rem;
        }

        .grid {
            display: block !important;
        }
        .grid > * {
            margin-bottom: 1rem;
        }

        * {
            color: #000 !important;
        }
        [style*="background-color: rgba("] {
            background-color: #f8f9fa !important;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .stat-ring-wrap .relative,
        .card {
            transition: none !important;
        }
        .stat-ring-wrap:hover .relative {
            transform: none !important;
        }
    }

    @media (max-width: 768px) {
        .card { border-radius: 0.5rem; }
        .text-2xl { font-size: 1.5rem !important; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    /* ---------- Print ---------- */
    function printStatistics() {
        window.print();
    }

    /* ---------- Public API ---------- */
    window.MaintenanceStats = {
        print: printStatistics,
    };

    /* ---------- Keyboard shortcuts ---------- */
    document.addEventListener('keydown', function (e) {
        // Ctrl/Cmd + P → print
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'p') {
            e.preventDefault();
            printStatistics();
        }
    });

    /* ---------- Toast (local helper, not global) ---------- */
    function toast(message, type = 'info') {
        const colors = {
            success: ['success', 'fa-check-circle'],
            error:   ['danger',  'fa-exclamation-circle'],
            warning: ['warning', 'fa-exclamation-triangle'],
            info:    ['info',    'fa-info-circle'],
        };
        const [key, icon] = colors[type] || colors.info;

        const el = document.createElement('div');
        el.className = 'fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg';
        el.setAttribute('role', 'status');
        el.style.cssText = `background-color: rgba(var(--${key}-rgb), 0.95); color: white; border-left: 4px solid var(--${key});`;

        el.innerHTML = `
            <div class="flex items-center gap-3">
                <i class="fas ${icon}"></i>
                <span>${escapeHtml(message)}</span>
            </div>`;

        document.body.appendChild(el);
        setTimeout(() => el.remove(), 4000);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }
})();
</script>
@endpush