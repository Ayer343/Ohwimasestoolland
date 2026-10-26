@extends('layouts.dev')

@section('title', 'Log Settings - Developer')

@section('content')
@include('developer.logs._nav')

@php
    $settings = $settings ?? [
        'retention_days' => 30,
        'levels_enabled' => ['emergency','alert','critical','error','warning','notice','info','debug'],
    ];
@endphp

<div class="log-settings">
    <div class="mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
            <i class="fas fa-cog mr-2" style="color: var(--primary);"></i>Log Settings
        </h1>
        <p class="text-sm mt-1" style="color: var(--text-secondary);">
            Read-only view — edit <code>config/logging.php</code> to change these values.
        </p>
    </div>

    <div class="rounded-xl p-5 mb-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <h3 class="font-semibold mb-3" style="color: var(--text-primary);">
            <i class="fas fa-clock mr-2" style="color: var(--primary);"></i>Retention
        </h3>
        <p class="text-sm" style="color: var(--text-primary);">
            Retention period: <strong>{{ (int) $settings['retention_days'] }} days</strong>
        </p>
    </div>

    <div class="rounded-xl p-5" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <h3 class="font-semibold mb-3" style="color: var(--text-primary);">
            <i class="fas fa-signal mr-2" style="color: var(--primary);"></i>Enabled Levels
        </h3>
        <div class="flex flex-wrap gap-2">
            @foreach(($settings['levels_enabled'] ?? []) as $lvl)
                <span class="text-xs px-3 py-1 rounded-full"
                      style="background-color: var(--bg-secondary); color: var(--text-primary);">
                    {{ ucfirst($lvl) }}
                </span>
            @endforeach
        </div>
    </div>
</div>

@push('styles')
<style>
    .log-settings { max-width: 1600px; margin: 0 auto; padding: 0 1rem; }
    @media (max-width: 768px) { .log-settings { padding: 0 .5rem; } }
</style>
@endpush
@endsection