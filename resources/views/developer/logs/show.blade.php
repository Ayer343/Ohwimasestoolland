@extends('layouts.dev')

@section('title', 'Log Entry - Developer')

@section('content')
@include('developer.logs._nav')

@php
    $log = $log ?? '';
@endphp

<div class="log-show">
    <div class="mb-6 flex justify-between items-center flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                <i class="fas fa-file-lines mr-2" style="color: var(--primary);"></i>Log Entry
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                Identifier: <code>{{ $log ?: '—' }}</code>
            </p>
        </div>
        <a href="{{ route('developer.logs.index') }}"
           class="px-4 py-2 rounded-lg text-sm"
           style="background-color: var(--bg-secondary); color: var(--text-secondary);">
            <i class="fas fa-arrow-left mr-2"></i>Back to logs
        </a>
    </div>

    <div class="rounded-xl p-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="text-sm" style="color: var(--text-secondary);">
            <i class="fas fa-info-circle mr-2"></i>
            The file-based log viewer does not resolve individual entries to models.
            To view a specific entry, use the search bar on the main logs page with a
            fragment of the message text.
        </div>
        <div class="mt-4 flex gap-2 flex-wrap">
            <a href="{{ route('developer.logs.index', ['search' => $log]) }}"
               class="px-4 py-2 rounded-lg text-sm"
               style="background-color: var(--primary); color: white;">
                <i class="fas fa-search mr-2"></i>Search for this entry
            </a>
            <a href="{{ route('developer.system-logs.index') }}"
               class="px-4 py-2 rounded-lg text-sm"
               style="background-color: var(--bg-secondary); color: var(--text-secondary);">
                <i class="fas fa-clipboard-list mr-2"></i>Open System Logs workflow
            </a>
        </div>
    </div>
</div>

@push('styles')
<style>
    .log-show { max-width: 1600px; margin: 0 auto; padding: 0 1rem; }
    @media (max-width: 768px) { .log-show { padding: 0 .5rem; } }
</style>
@endpush
@endsection