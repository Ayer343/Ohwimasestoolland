@extends('layouts.dev')

@section('title', 'Log Files - Developer')

@section('content')
@include('developer.logs._nav')

@php
    $entries     = $entries     ?? [];
    $files       = $files       ?? [];
    $total       = (int) ($total ?? 0);
    $page        = (int) ($page ?? 1);
    $perPage     = (int) ($perPage ?? 100);
    $level       = $level       ?? null;
    $search      = $search      ?? null;
    $currentFile = $currentFile ?? 'laravel.log';

    $lastPage = max(1, (int) ceil($total / $perPage));

    $levelBadge = [
        'emergency' => 'bg-red-500/20 text-red-500',
        'alert'     => 'bg-red-500/20 text-red-500',
        'critical'  => 'bg-red-500/20 text-red-500',
        'error'     => 'bg-red-500/20 text-red-400',
        'warning'   => 'bg-yellow-500/20 text-yellow-500',
        'notice'    => 'bg-blue-500/20 text-blue-400',
        'info'      => 'bg-blue-500/20 text-blue-500',
        'debug'     => 'bg-gray-500/20 text-gray-400',
        'unknown'   => 'bg-gray-500/20 text-gray-500',
    ];

    $formatBytes = function ($bytes) {
        $bytes = (int) $bytes;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    };
@endphp

<div class="log-files">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex justify-between items-center flex-wrap gap-3">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-file-lines mr-2" style="color: var(--primary);"></i>Log Files
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Raw log file viewer — {{ $currentFile }}
                    <span class="ml-2 text-xs">{{ number_format($total) }} entries</span>
                </p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('developer.system-logs.index') }}"
                   class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                   style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-clipboard-list mr-2"></i>System Logs
                </a>
                <a href="{{ route('developer.logs.export') }}?format=csv"
                   class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                   style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-file-export mr-2"></i>Export
                </a>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="rounded-xl p-4 mb-6"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs mb-1" style="color: var(--text-secondary);">File</label>
                <select name="file" class="w-full px-3 py-2 rounded-lg text-sm"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    @foreach($files as $f)
                        <option value="{{ $f['name'] }}" @selected($currentFile === $f['name'])>
                            {{ $f['name'] }} ({{ $formatBytes($f['size']) }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs mb-1" style="color: var(--text-secondary);">Level</label>
                <select name="level" class="w-full px-3 py-2 rounded-lg text-sm"
                        style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
                    <option value="">All levels</option>
                    @foreach(['emergency','alert','critical','error','warning','notice','info','debug'] as $lvl)
                        <option value="{{ $lvl }}" @selected($level === $lvl)>{{ ucfirst($lvl) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="lg:col-span-2">
                <label class="block text-xs mb-1" style="color: var(--text-secondary);">Search</label>
                <input type="text" name="search" value="{{ $search }}"
                       placeholder="Search message text…"
                       class="w-full px-3 py-2 rounded-lg text-sm"
                       style="background-color: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color);">
            </div>

            <div class="lg:col-span-4 flex justify-end gap-2">
                <a href="{{ route('developer.logs.index') }}"
                   class="px-4 py-2 rounded-lg text-sm"
                   style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                    <i class="fas fa-times mr-2"></i>Reset
                </a>
                <button type="submit"
                        class="px-4 py-2 rounded-lg text-sm"
                        style="background-color: var(--primary); color: white;">
                    <i class="fas fa-filter mr-2"></i>Apply
                </button>
            </div>
        </form>
    </div>

    <!-- Entries table -->
    <div class="rounded-xl overflow-hidden"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead style="background-color: var(--bg-secondary);">
                    <tr style="color: var(--text-secondary);">
                        <th class="p-3 text-left w-44">Timestamp</th>
                        <th class="p-3 text-left w-24">Level</th>
                        <th class="p-3 text-left">Message</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                        @php
                            $lvl = strtolower($entry['level'] ?? 'unknown');
                            $badge = $levelBadge[$lvl] ?? $levelBadge['unknown'];
                        @endphp
                        <tr class="border-t align-top"
                            style="border-color: var(--border-color); color: var(--text-primary);">
                            <td class="p-3 font-mono text-xs whitespace-nowrap"
                                style="color: var(--text-secondary);">
                                {{ $entry['timestamp'] ?? '—' }}
                            </td>
                            <td class="p-3">
                                <span class="text-xs px-2 py-1 rounded-full {{ $badge }}">
                                    {{ strtoupper($lvl) }}
                                </span>
                            </td>
                            <td class="p-3 font-mono text-xs break-all">
                                {{ \Illuminate\Support\Str::limit($entry['message'] ?? '', 800) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="p-6 text-center text-sm" style="color: var(--text-secondary);">
                                <i class="fas fa-inbox text-3xl mb-2 opacity-50"></i>
                                <p>No log entries match the current filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($lastPage > 1)
            <div class="p-4 border-t flex items-center justify-between gap-3 flex-wrap"
                 style="border-color: var(--border-color);">
                <div class="text-xs" style="color: var(--text-secondary);">
                    Page {{ $page }} of {{ $lastPage }} · {{ number_format($total) }} entries
                </div>
                <div class="flex gap-2">
                    @php
                        $baseQuery = array_filter([
                            'file'   => $currentFile,
                            'level'  => $level,
                            'search' => $search,
                        ], fn ($v) => $v !== null && $v !== '');
                    @endphp

                    @if($page > 1)
                        <a href="{{ route('developer.logs.index', array_merge($baseQuery, ['page' => $page - 1])) }}"
                           class="px-3 py-1.5 rounded-lg text-xs"
                           style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                            <i class="fas fa-chevron-left mr-1"></i>Prev
                        </a>
                    @endif

                    @if($page < $lastPage)
                        <a href="{{ route('developer.logs.index', array_merge($baseQuery, ['page' => $page + 1])) }}"
                           class="px-3 py-1.5 rounded-lg text-xs"
                           style="background-color: var(--primary); color: white;">
                            Next<i class="fas fa-chevron-right ml-1"></i>
                        </a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

@push('styles')
<style>
    .log-files { max-width: 1600px; margin: 0 auto; padding: 0 1rem; }
    @media (max-width: 768px) { .log-files { padding: 0 .5rem; } }
</style>
@endpush
@endsection