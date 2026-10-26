{{-- resources/views/developer/health/report.blade.php --}}
@extends('layouts.dev')

@section('title', 'Health Report - Developer')

@section('content')
@include('developer.health._nav')

@php
    $report     = is_array($report ?? null) ? $report : [];
    $overall    = $report['overall_health']   ?? ['status' => 'unknown', 'score' => 0, 'components' => []];
    $metrics    = $report['system_metrics']   ?? [];
    $components = $report['component_status'] ?? [];
    $recs       = $report['recommendations']  ?? [];

    $score  = (int) ($overall['score'] ?? 0);
    $status = strtolower($overall['status'] ?? 'unknown');

    $statusColors = [
        'healthy'     => ['text' => 'text-green-500',  'bg' => 'bg-green-500/10',  'border' => 'border-green-500/30',  'ring' => '#10b981'],
        'operational' => ['text' => 'text-green-500',  'bg' => 'bg-green-500/10',  'border' => 'border-green-500/30',  'ring' => '#10b981'],
        'degraded'    => ['text' => 'text-yellow-500', 'bg' => 'bg-yellow-500/10', 'border' => 'border-yellow-500/30', 'ring' => '#f59e0b'],
        'warning'     => ['text' => 'text-yellow-500', 'bg' => 'bg-yellow-500/10', 'border' => 'border-yellow-500/30', 'ring' => '#f59e0b'],
        'critical'    => ['text' => 'text-red-500',    'bg' => 'bg-red-500/10',    'border' => 'border-red-500/30',    'ring' => '#ef4444'],
        'down'        => ['text' => 'text-red-500',    'bg' => 'bg-red-500/10',    'border' => 'border-red-500/30',    'ring' => '#ef4444'],
        'unknown'     => ['text' => 'text-gray-400',   'bg' => 'bg-gray-500/10',   'border' => 'border-gray-500/30',   'ring' => '#9ca3af'],
    ];

    $style = $statusColors[$status] ?? $statusColors['unknown'];

    $fmt = function ($v, $unit = '%', $decimals = 2) {
        return is_numeric($v) ? number_format((float) $v, $decimals) . $unit : 'N/A';
    };

    $reportId = $report['id'] ?? null;
@endphp

<div class="health-monitor max-w-5xl mx-auto">

    {{-- Header --}}
    <div class="mb-6 flex justify-between items-start flex-wrap gap-4">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                <i class="fas fa-file-medical mr-2" style="color: var(--primary);"></i>Health Report
            </h1>
            <div class="text-xs mt-2 space-y-0.5" style="color: var(--text-secondary);">
                <div>ID: <code class="px-1.5 py-0.5 rounded" style="background-color: var(--bg-secondary);">{{ $reportId ?? '—' }}</code></div>
                <div>Generated: {{ $report['generated_at'] ?? '—' }}</div>
                <div>By user: #{{ $report['generated_by'] ?? '—' }}</div>
            </div>
        </div>

        <div class="flex gap-2">
            <button onclick="window.print()"
                    class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                    style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                <i class="fas fa-print mr-2"></i>Print
            </button>

            @if($reportId)
                <a href="{{ route('developer.health.report.download', $reportId) }}"
                   class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                   style="background-color: var(--primary); color: white;">
                    <i class="fas fa-download mr-2"></i>Download JSON
                </a>
            @endif
        </div>
    </div>

    {{-- Overall Score Card --}}
    <div class="rounded-xl p-6 mb-6 flex items-center gap-6 flex-wrap"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="text-center px-6 py-2 rounded-xl {{ $style['bg'] }} border {{ $style['border'] }}">
            <p class="text-4xl font-bold {{ $style['text'] }}">{{ $score }}</p>
            <p class="text-xs mt-1" style="color: var(--text-secondary);">out of 100</p>
        </div>
        <div class="flex-1 min-w-[220px]">
            <p class="text-lg font-semibold {{ $style['text'] }}">
                <i class="fas fa-circle text-xs mr-1"></i>{{ ucfirst($status) }}
            </p>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Overall system health at generation time.
            </p>
        </div>
    </div>

    {{-- System Metrics --}}
    <div class="rounded-xl p-6 mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-tachometer-alt mr-2" style="color: var(--primary);"></i>System Metrics
        </h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-lg p-4" style="background-color: var(--bg-secondary);">
                <div class="flex items-center gap-2 mb-1">
                    <i class="fas fa-microchip" style="color: var(--primary);"></i>
                    <p class="text-xs" style="color: var(--text-secondary);">CPU</p>
                </div>
                <p class="text-xl font-bold" style="color: var(--text-primary);">
                    {{ $fmt($metrics['cpu'] ?? null) }}
                </p>
            </div>

            <div class="rounded-lg p-4" style="background-color: var(--bg-secondary);">
                <div class="flex items-center gap-2 mb-1">
                    <i class="fas fa-memory" style="color: var(--primary);"></i>
                    <p class="text-xs" style="color: var(--text-secondary);">Memory</p>
                </div>
                <p class="text-xl font-bold" style="color: var(--text-primary);">
                    {{ $fmt($metrics['memory'] ?? null) }}
                </p>
            </div>

            <div class="rounded-lg p-4" style="background-color: var(--bg-secondary);">
                <div class="flex items-center gap-2 mb-1">
                    <i class="fas fa-hdd" style="color: var(--primary);"></i>
                    <p class="text-xs" style="color: var(--text-secondary);">Disk</p>
                </div>
                <p class="text-xl font-bold" style="color: var(--text-primary);">
                    {{ $fmt($metrics['disk'] ?? null) }}
                </p>
            </div>

            <div class="rounded-lg p-4" style="background-color: var(--bg-secondary);">
                <div class="flex items-center gap-2 mb-1">
                    <i class="fas fa-clock" style="color: var(--primary);"></i>
                    <p class="text-xs" style="color: var(--text-secondary);">Uptime</p>
                </div>
                <p class="text-sm font-medium mt-1" style="color: var(--text-primary);">
                    {{ $metrics['uptime'] ?? 'N/A' }}
                </p>
            </div>
        </div>
    </div>

    {{-- Component Status --}}
    <div class="rounded-xl p-6 mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
            <i class="fas fa-server mr-2" style="color: var(--primary);"></i>Component Status
        </h2>

        @if(!empty($components))
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($components as $name => $info)
                    @php
                        $cStatus = strtolower($info['status'] ?? 'unknown');
                        $cKey    = $cStatus === 'operational' ? 'healthy'
                                 : (in_array($cStatus, ['degraded','warning'], true) ? 'degraded' : 'critical');
                        $cStyle  = $statusColors[$cKey];
                    @endphp
                    <div class="p-3 rounded-lg" style="background-color: var(--bg-secondary);">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-medium" style="color: var(--text-primary);">{{ ucfirst($name) }}</p>
                                <p class="text-xs mt-0.5" style="color: var(--text-secondary);">
                                    {{ $info['name'] ?? '—' }}
                                    @if(!empty($info['latency']))
                                        · latency {{ $info['latency'] }}
                                    @endif
                                </p>
                            </div>
                            <span class="text-xs font-medium {{ $cStyle['text'] }}">
                                {{ ucfirst($cStatus) }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm" style="color: var(--text-secondary);">No component data available.</p>
        @endif
    </div>

    {{-- Overall Health Components Snapshot (from overall_health.components) --}}
    @if(!empty($overall['components']))
        <div class="rounded-xl p-6 mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-check-double mr-2" style="color: var(--primary);"></i>Health Snapshot
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach($overall['components'] as $name => $cStatusRaw)
                    @php
                        $cStatus = strtolower($cStatusRaw);
                        $cStyle  = $statusColors[$cStatus] ?? $statusColors['unknown'];
                    @endphp
                    <div class="p-3 rounded-lg text-center" style="background-color: var(--bg-secondary);">
                        <p class="text-xs mb-1" style="color: var(--text-secondary);">{{ ucfirst($name) }}</p>
                        <p class="text-sm font-semibold {{ $cStyle['text'] }}">{{ ucfirst($cStatus) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Recommendations --}}
    @if(!empty($recs))
        <div class="rounded-xl p-6 mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
            <h2 class="text-lg font-semibold mb-3" style="color: var(--text-primary);">
                <i class="fas fa-lightbulb mr-2" style="color: var(--primary);"></i>Recommendations
            </h2>
            <ul class="list-disc pl-5 space-y-1 text-sm" style="color: var(--text-secondary);">
                @foreach($recs as $rec)
                    <li>{{ $rec }}</li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="rounded-xl p-4 mb-6 text-sm flex items-center gap-2"
             style="background-color: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-secondary);">
            <i class="fas fa-check-circle text-green-500"></i>
            <span>No recommendations — system is healthy.</span>
        </div>
    @endif

    {{-- Footer / Back link --}}
    <div class="text-xs text-center mt-6 flex justify-center gap-4" style="color: var(--text-secondary);">
        <a href="{{ route('developer.health.reports.index') }}" class="hover:underline">
            <i class="fas fa-arrow-left mr-1"></i>Back to all reports
        </a>
        <a href="{{ route('developer.health.index') }}" class="hover:underline">
            <i class="fas fa-heartbeat mr-1"></i>Health Dashboard
        </a>
    </div>

</div>
@endsection