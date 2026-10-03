@extends('layouts.dev')

@section('title', 'Backup Details - Developer')

@section('content')
<div class="backup-details">
    <div class="mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-archive mr-2" style="color: var(--primary);"></i>Backup Details
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    View backup information
                </p>
            </div>
            <div>
                <a href="{{ route('developer.database.index') }}" class="px-4 py-2 rounded-lg text-sm transition-all hover:opacity-80"
                   style="background-color: var(--bg-secondary); color: var(--text-secondary); text-decoration: none;">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Database
                </a>
            </div>
        </div>
    </div>

    <div class="backup-card rounded-xl p-6" style="background-color: var(--card-bg); border: 1px solid var(--border-color);">
        <div class="text-center py-8">
            <i class="fas fa-database text-5xl mb-4" style="color: var(--primary);"></i>
            <h2 class="text-xl font-semibold mb-2" style="color: var(--text-primary);">Backup Details</h2>
            <p class="text-sm mb-4" style="color: var(--text-secondary);">
                Backup file information is being loaded...
            </p>
            <div class="flex justify-center gap-3">
                <a href="{{ route('developer.database.index') }}" class="px-4 py-2 rounded-lg text-sm"
                   style="background-color: var(--bg-secondary); color: var(--text-secondary); text-decoration: none;">
                    <i class="fas fa-arrow-left mr-1"></i>Go Back
                </a>
                <a href="{{ route('developer.database.backups') }}" class="px-4 py-2 rounded-lg text-sm"
                   style="background-color: var(--primary); color: white; text-decoration: none;">
                    <i class="fas fa-sync-alt mr-1"></i>Refresh
                </a>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .backup-details {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 1rem;
    }
    
    .backup-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    @media (max-width: 768px) {
        .backup-details {
            padding: 0 0.5rem;
        }
    }
</style>
@endpush
@endsection