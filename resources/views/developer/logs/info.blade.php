@extends('layouts.dev')

@section('title', 'Info Logs - Developer')

@section('content')
@include('developer.logs._nav')

@php
    $entries    = $entries    ?? [];
    $level      = $level      ?? 'info';
    $total      = (int) ($total ?? 0);
    $levelIcon  = 'fa-circle-info';
    $levelColor = '#3b82f6';
@endphp

<div class="log-level-view">
    <div class="mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
            <i class="fas {{ $levelIcon }} mr-2" style="color: {{ $levelColor }};"></i>
            Info Logs
        </h1>
        <p class="text-sm mt-1" style="color: var(--text-secondary);">
            {{ number_format($total) }} entr{{ $total === 1 ? 'y' : 'ies' }} · showing newest {{ count($entries) }}
        </p>
    </div>

    <div class="rounded-xl overflow-hidden"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <table class="w-full text-sm">
            <thead style="background-color: var(--bg-secondary);">
                <tr style="color: var(--text-secondary);">
                    <th class="p-3 text-left w-44">Timestamp</th>
                    <th class="p-3 text-left">Message</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entries as $entry)
                    <tr class="border-t" style="border-color: var(--border-color); color: var(--text-primary);">
                        <td class="p-3 font-mono text-xs whitespace-nowrap" style="color: var(--text-secondary);">
                            {{ $entry['timestamp'] ?? '—' }}
                        </td>
                        <td class="p-3 font-mono text-xs break-all">
                            {{ \Illuminate\Support\Str::limit($entry['message'] ?? '', 800) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="p-6 text-center text-sm" style="color: var(--text-secondary);">
                            <i class="fas fa-check-circle text-3xl mb-2 opacity-50"></i>
                            <p>No info entries.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex justify-end">
        <a href="{{ route('developer.logs.index', ['level' => $level]) }}"
           class="px-4 py-2 rounded-lg text-sm"
           style="background-color: var(--bg-secondary); color: var(--text-secondary);">
            <i class="fas fa-list mr-2"></i>View in main log viewer
        </a>
    </div>
</div>

@push('styles')
<style>
    .log-level-view { max-width: 1600px; margin: 0 auto; padding: 0 1rem; }
    @media (max-width: 768px) { .log-level-view { padding: 0 .5rem; } }
</style>
@endpush
@endsection