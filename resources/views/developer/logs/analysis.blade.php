@extends('layouts.dev')

@section('title', 'Log Analysis - Developer')

@section('content')
@include('developer.logs._nav')

@php
    $stats   = $stats ?? ['total' => 0, 'by_level' => [], 'by_date' => [], 'generated_at' => null];
    $byLevel = $stats['by_level'] ?? [];
    $byDate  = $stats['by_date']  ?? [];

    $levelColor = [
        'emergency' => '#ef4444', 'alert' => '#ef4444', 'critical' => '#ef4444',
        'error' => '#f87171', 'warning' => '#f59e0b', 'notice' => '#60a5fa',
        'info' => '#3b82f6', 'debug' => '#9ca3af', 'unknown' => '#6b7280',
    ];

    $totalByLevel = array_sum($byLevel) ?: 1;
    arsort($byLevel);

    $topDays = $byDate;
    arsort($topDays);
    $topDays = array_slice($topDays, 0, 10, true);
@endphp

<div class="log-analysis">
    <div class="mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
            <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i>Log Analysis
        </h1>
        <p class="text-sm mt-1" style="color: var(--text-secondary);">
            Distribution across levels and days
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Level distribution -->
        <div class="rounded-xl p-5" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <h3 class="font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-layer-group mr-2" style="color: var(--primary);"></i>By Level
            </h3>
            @forelse($byLevel as $lvl => $count)
                @php $pct = round($count / $totalByLevel * 100, 2); @endphp
                <div class="mb-3">
                    <div class="flex justify-between text-xs mb-1" style="color: var(--text-secondary);">
                        <span>{{ ucfirst($lvl) }}</span>
                        <span>{{ number_format($count) }} · {{ $pct }}%</span>
                    </div>
                    <div class="h-3 rounded-full overflow-hidden" style="background-color: var(--bg-secondary);">
                        <div class="h-full rounded-full"
                             style="width: {{ $pct }}%; background-color: {{ $levelColor[$lvl] ?? '#6b7280' }};"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm" style="color: var(--text-secondary);">No data.</p>
            @endforelse
        </div>

        <!-- Top days -->
        <div class="rounded-xl p-5" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <h3 class="font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-calendar-day mr-2" style="color: var(--primary);"></i>Top Days
            </h3>
            <table class="w-full text-sm">
                <thead style="color: var(--text-secondary);">
                    <tr><th class="text-left pb-2">Date</th><th class="text-right pb-2">Count</th></tr>
                </thead>
                <tbody>
                    @forelse($topDays as $day => $count)
                        <tr class="border-t" style="border-color: var(--border-color); color: var(--text-primary);">
                            <td class="py-2">{{ $day }}</td>
                            <td class="py-2 text-right">{{ number_format($count) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="py-4 text-center" style="color: var(--text-secondary);">No data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('styles')
<style>
    .log-analysis { max-width: 1600px; margin: 0 auto; padding: 0 1rem; }
    @media (max-width: 768px) { .log-analysis { padding: 0 .5rem; } }
</style>
@endpush
@endsection