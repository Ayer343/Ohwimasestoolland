{{-- resources/views/landlord/waste/history.blade.php --}}

@extends('layouts.landlord')

@section('title', 'Collection History')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="max-w-7xl mx-auto">

        <!-- Flash messages -->
        @if(session('success'))
            <div class="mb-4 px-4 py-3 rounded-lg" style="background-color: rgba(34,197,94,0.1); color: #16a34a; border: 1px solid #16a34a;">
                <i class="fas fa-check-circle mr-2"></i> {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 px-4 py-3 rounded-lg" style="background-color: rgba(239,68,68,0.1); color: #dc2626; border: 1px solid #dc2626;">
                <i class="fas fa-exclamation-circle mr-2"></i> {{ session('error') }}
            </div>
        @endif

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold" style="color: var(--text-primary);">
                    <i class="fas fa-history mr-2" style="color: var(--primary);"></i>
                    Collection History
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    View completed waste collection history for your properties
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('landlord.waste.approvals') }}" class="btn-warning">
                    <i class="fas fa-check-double mr-2"></i> Pending Approvals
                </a>
                <a href="{{ route('landlord.waste.requests') }}" class="btn-info">
                    <i class="fas fa-list mr-2"></i> All Requests
                </a>
            </div>
        </div>

        <!-- Stats (weight card removed) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ $stats['total_collections'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Total Collections</div>
            </div>
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold text-blue-500">{{ $stats['this_month'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">This Month</div>
            </div>
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold text-orange-500">
                    @if(!empty($stats['last_collection']) && $stats['last_collection']->completed_at)
                        {{ $stats['last_collection']->completed_at->format('M d') }}
                    @else
                        N/A
                    @endif
                </div>
                <div class="text-xs" style="color: var(--text-secondary);">Last Collection</div>
            </div>
        </div>

        <!-- History Table (weight column removed) -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead style="background-color: var(--bg-secondary);">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                                Property
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                                Waste Type
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                                Completed
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                                Duration
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                                Assigned To
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="border-color: var(--border-color);">
                        @forelse($history as $request)
                            <tr style="background-color: var(--bg-secondary);">
                                <td class="px-4 py-3">
                                    <div>
                                        <div class="font-medium" style="color: var(--text-primary);">
                                            {{ $request->property->property_name ?? 'N/A' }}
                                        </div>
                                        <div class="text-xs" style="color: var(--text-secondary);">
                                            {{ $request->property->digital_address ?? 'No address' }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded text-xs font-medium
                                        @if($request->waste_type == 'general') bg-gray-100 text-gray-600
                                        @elseif($request->waste_type == 'recyclable') bg-green-100 text-green-600
                                        @elseif($request->waste_type == 'organic') bg-yellow-100 text-yellow-600
                                        @elseif($request->waste_type == 'hazardous') bg-red-100 text-red-600
                                        @elseif($request->waste_type == 'bulk') bg-purple-100 text-purple-600
                                        @else bg-gray-100 text-gray-600 @endif">
                                        {{ ucfirst($request->waste_type ?? 'General') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm" style="color: var(--text-primary);">
                                        {{ optional($request->completed_at)->format('M d, Y') ?? 'N/A' }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ optional($request->completed_at)->format('g:i A') ?? '' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-sm" style="color: var(--text-primary);">
                                        {{ $request->duration_formatted ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm" style="color: var(--text-primary);">
                                        {{ $request->assignedTo->full_name ?? 'N/A' }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        {{ $request->assignedTo->role ?? '' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('landlord.waste.request.show', ['wasteCollectionRequest' => $request->id]) }}"
                                       class="text-sm hover:underline px-2 py-1 rounded"
                                       style="color: var(--primary); background-color: rgba(var(--primary-rgb, 59,130,246), 0.1);">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center" style="color: var(--text-secondary);">
                                    <i class="fas fa-history text-4xl mb-3 block" style="color: var(--text-secondary); opacity: 0.3;"></i>
                                    <p>No collection history found</p>
                                    <p class="text-xs mt-1">Completed collections will appear here</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($history->hasPages())
                <div class="px-4 py-3 border-t" style="border-color: var(--border-color);">
                    {{ $history->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .btn-primary, .btn-secondary, .btn-info, .btn-warning {
        display: inline-flex;
        align-items: center;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
        text-decoration: none;
    }
    .btn-primary {
        background-color: var(--primary);
        color: white;
    }
    .btn-primary:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: white;
        text-decoration: none;
    }
    .btn-secondary {
        background-color: var(--bg-secondary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }
    .btn-secondary:hover {
        background-color: var(--bg-secondary);
        opacity: 0.8;
        text-decoration: none;
        color: var(--text-primary);
    }
    .btn-info {
        background-color: var(--info);
        color: white;
    }
    .btn-info:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: white;
        text-decoration: none;
    }
    .btn-warning {
        background-color: #f59e0b;
        color: white;
    }
    .btn-warning:hover {
        opacity: 0.9;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        color: white;
        text-decoration: none;
    }
    .card {
        background-color: var(--card-bg);
        border-radius: 0.75rem;
        border: 1px solid var(--border-color);
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    }
</style>
@endpush