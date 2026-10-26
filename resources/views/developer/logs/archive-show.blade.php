@extends('layouts.dev')

@section('title', 'Archive ' . ($archive ?? '') . ' - Developer')

@section('content')
@include('developer.logs._nav')

@php
    $archive = $archive ?? '';
    $content = $content ?? '';
@endphp

<div class="log-archive-show">
    <div class="mb-6 flex justify-between items-center flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                <i class="fas fa-file-zipper mr-2" style="color: var(--primary);"></i>{{ $archive }}
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                {{ number_format(strlen($content)) }} bytes
            </p>
        </div>
        <a href="{{ route('developer.logs.archives') }}"
           class="px-4 py-2 rounded-lg text-sm"
           style="background-color: var(--bg-secondary); color: var(--text-secondary);">
            <i class="fas fa-arrow-left mr-2"></i>Back to archives
        </a>
    </div>

    <div class="rounded-xl p-4 font-mono text-xs overflow-auto"
         style="background-color: var(--card-bg); border: 1px solid var(--border-color);
                color: var(--text-primary); max-height: 75vh; white-space: pre-wrap;">
        {{ $content ?: 'Archive is empty.' }}
    </div>
</div>

@push('styles')
<style>
    .log-archive-show { max-width: 1600px; margin: 0 auto; padding: 0 1rem; }
    @media (max-width: 768px) { .log-archive-show { padding: 0 .5rem; } }
</style>
@endpush
@endsection