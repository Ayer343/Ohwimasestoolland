@extends('layouts.dev')

@section('title', 'Log Trends - Developer')

@section('content')
@include('developer.logs._nav')

@php
    $trends = $trends ?? [];
    $labels = array_keys($trends);
    $values = array_values($trends);
    $max    = max(1, $values ? max($values) : 1);
@endphp

<div class="log-trends">
    <div class="mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
            <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>Log Trends
        </h1>
        <p class="text-sm mt-1" style="color: var(--text-secondary);">
            Entries per day over the last {{ count($trends) }} days
        </p>
    </div>

    <div class="rounded-xl p-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <canvas id="trendsChart" height="120"></canvas>
    </div>

    <div class="rounded-xl p-5 mt-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <h3 class="font-semibold mb-3" style="color: var(--text-primary);">
            <i class="fas fa-table mr-2" style="color: var(--primary);"></i>Raw Counts
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead style="background-color: var(--bg-secondary);">
                    <tr style="color: var(--text-secondary);">
                        <th class="p-2 text-left">Date</th>
                        <th class="p-2 text-right">Entries</th>
                        <th class="p-2 text-right">Relative</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($trends as $day => $count)
                        <tr class="border-t" style="border-color: var(--border-color); color: var(--text-primary);">
                            <td class="p-2">{{ $day }}</td>
                            <td class="p-2 text-right">{{ number_format($count) }}</td>
                            <td class="p-2 text-right" style="color: var(--text-secondary);">
                                {{ round($count / $max * 100, 1) }}%
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="p-4 text-center" style="color: var(--text-secondary);">No data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('styles')
<style>
    .log-trends { max-width: 1600px; margin: 0 auto; padding: 0 1rem; }
    @media (max-width: 768px) { .log-trends { padding: 0 .5rem; } }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('trendsChart');
    if (!canvas || typeof Chart === 'undefined') return;

    const getCss = (v, fb) =>
        getComputedStyle(document.documentElement).getPropertyValue(v).trim() || fb;

    const primary       = getCss('--primary', '#3b82f6');
    const textSecondary = getCss('--text-secondary', '#94a3b8');

    new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: @json($labels),
            datasets: [{
                label: 'Entries',
                data: @json($values),
                borderColor: primary,
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: @json(count($values) === 1 ? 6 : 3),
                pointHoverRadius: 6,
                spanGaps: true,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { labels: { color: textSecondary } },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { color: textSecondary },
                    grid: { color: 'rgba(148, 163, 184, 0.1)' },
                },
                x: {
                    ticks: { color: textSecondary, maxRotation: 0 },
                    grid: { color: 'rgba(148, 163, 184, 0.1)' },
                },
            },
        },
    });
});
</script>
@endpush
@endsection