@extends('layouts.dev')

@section('title', 'Log Overview - Developer')

@section('content')
@include('developer.logs._nav')

@php
    $stats  = $stats  ?? ['total' => 0, 'by_level' => [], 'by_date' => [], 'generated_at' => null];
    $files  = $files  ?? [];
    $recent = $recent ?? [];

    $formatBytes = function ($bytes) {
        $bytes = (int) $bytes;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    };

    $levelColor = [
        'emergency' => '#ef4444', 'alert' => '#ef4444', 'critical' => '#ef4444',
        'error' => '#f87171', 'warning' => '#f59e0b', 'notice' => '#60a5fa',
        'info' => '#3b82f6', 'debug' => '#9ca3af', 'unknown' => '#6b7280',
    ];
@endphp

<div class="log-overview">
    <div class="mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
            <i class="fas fa-chart-pie mr-2" style="color: var(--primary);"></i>Log Overview
        </h1>
        <p class="text-sm mt-1" style="color: var(--text-secondary);">
            Snapshot of parsed log statistics
            @if($stats['generated_at'] ?? null)
                · generated {{ $stats['generated_at'] }}
            @endif
        </p>
    </div>

    <!-- Totals -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="rounded-xl p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <div class="flex items-center gap-2 mb-1">
                <i class="fas fa-database" style="color: var(--primary);"></i>
                <span class="text-xs" style="color: var(--text-secondary);">Total</span>
            </div>
            <p class="text-2xl font-bold" style="color: var(--text-primary);">{{ number_format($stats['total'] ?? 0) }}</p>
        </div>

        @foreach(['error', 'warning', 'info'] as $lvl)
            <div class="rounded-xl p-4" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
                <div class="flex items-center gap-2 mb-1">
                    <i class="fas fa-circle" style="color: {{ $levelColor[$lvl] ?? '#6b7280' }};"></i>
                    <span class="text-xs" style="color: var(--text-secondary);">{{ ucfirst($lvl) }}</span>
                </div>
                <p class="text-2xl font-bold" style="color: var(--text-primary);">
                    {{ number_format($stats['by_level'][$lvl] ?? 0) }}
                </p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Level breakdown -->
        <div class="rounded-xl p-5" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <h3 class="font-semibold mb-3" style="color: var(--text-primary);">
                <i class="fas fa-layer-group mr-2" style="color: var(--primary);"></i>By Level
            </h3>
            @php
                $totalByLevel = array_sum($stats['by_level'] ?? []) ?: 1;
            @endphp
            <div class="space-y-2">
                @forelse(($stats['by_level'] ?? []) as $lvl => $count)
                    @php $pct = round($count / $totalByLevel * 100, 1); @endphp
                    <div>
                        <div class="flex justify-between text-xs mb-1" style="color: var(--text-secondary);">
                            <span>{{ ucfirst($lvl) }}</span>
                            <span>{{ number_format($count) }} ({{ $pct }}%)</span>
                        </div>
                        <div class="h-2 rounded-full overflow-hidden" style="background-color: var(--bg-secondary);">
                            <div class="h-full" style="width: {{ $pct }}%; background-color: {{ $levelColor[$lvl] ?? '#6b7280' }};"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm" style="color: var(--text-secondary);">No data.</p>
                @endforelse
            </div>
        </div>

        <!-- Files -->
        <div class="rounded-xl p-5" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <h3 class="font-semibold mb-3" style="color: var(--text-primary);">
                <i class="fas fa-folder-open mr-2" style="color: var(--primary);"></i>Log Files
            </h3>
            <ul class="space-y-2">
                @forelse($files as $f)
                    <li class="flex justify-between items-center p-2 rounded-lg text-sm"
                        style="background-color: var(--bg-secondary);">
                        <a href="{{ route('developer.logs.index', ['file' => $f['name']]) }}"
                           class="hover:underline" style="color: var(--primary);">
                            <i class="fas fa-file mr-2"></i>{{ $f['name'] }}
                        </a>
                        <span class="text-xs" style="color: var(--text-secondary);">
                            {{ $formatBytes($f['size']) }} · {{ $f['modified'] }}
                        </span>
                    </li>
                @empty
                    <li class="text-sm" style="color: var(--text-secondary);">No log files found.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <!-- Recent entries -->
    <div class="rounded-xl p-5 mt-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <h3 class="font-semibold mb-3" style="color: var(--text-primary);">
            <i class="fas fa-clock-rotate-left mr-2" style="color: var(--primary);"></i>Recent Entries
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead style="background-color: var(--bg-secondary);">
                    <tr style="color: var(--text-secondary);">
                        <th class="p-2 text-left w-44">Timestamp</th>
                        <th class="p-2 text-left w-24">Level</th>
                        <th class="p-2 text-left">Message</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recent as $entry)
                        @php $lvl = strtolower($entry['level'] ?? 'unknown'); @endphp
                        <tr class="border-t" style="border-color: var(--border-color); color: var(--text-primary);">
                            <td class="p-2 font-mono text-xs whitespace-nowrap" style="color: var(--text-secondary);">
                                {{ $entry['timestamp'] ?? '—' }}
                            </td>
                            <td class="p-2">
                                <span class="text-xs px-2 py-1 rounded-full"
                                      style="background-color: {{ ($levelColor[$lvl] ?? '#6b7280') }}22; color: {{ $levelColor[$lvl] ?? '#6b7280' }};">
                                    {{ strtoupper($lvl) }}
                                </span>
                            </td>
                            <td class="p-2 font-mono text-xs break-all">
                                {{ \Illuminate\Support\Str::limit($entry['message'] ?? '', 300) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="p-6 text-center text-sm" style="color: var(--text-secondary);">
                                No recent entries.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('styles')
<style>
    .log-overview { max-width: 1600px; margin: 0 auto; padding: 0 1rem; }
    @media (max-width: 768px) { .log-overview { padding: 0 .5rem; } }
</style>
@endpush
@endsection