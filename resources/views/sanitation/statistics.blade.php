{{-- resources/views/sanitation/statistics.blade.php --}}

@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin() || $user->isSuperAdmin();
    $layout = $isAdmin ? 'layouts.app' : 'layouts.san';
@endphp

@extends($layout)

@section('title', 'Sanitation Statistics')

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
                    <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i>
                    Sanitation Statistics
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    System-wide metrics and performance overview
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('sanitation.reports') }}" class="btn-info">
                    <i class="fas fa-file-alt mr-2"></i> Reports
                </a>
                <a href="{{ route('sanitation.dashboard') }}" class="btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- System Stats -->
        @php
            $systemStats = is_array($stats) ? $stats : [];
        @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ $systemStats['total_properties'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Total Properties</div>
            </div>
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold text-blue-500">{{ $systemStats['linked_properties'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Linked Properties</div>
            </div>
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold text-yellow-500">{{ $systemStats['total_requests'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Total Requests</div>
            </div>
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold text-green-500">{{ $systemStats['completed_today'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Completed Today</div>
            </div>
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold text-orange-500">{{ $systemStats['pending_requests'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Pending Requests</div>
            </div>
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold text-indigo-500">{{ $systemStats['active_requests'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Active Requests</div>
            </div>
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold text-purple-500">{{ $systemStats['active_personnel'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Active Personnel</div>
            </div>
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold text-cyan-500">{{ $systemStats['active_workers'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Active Workers</div>
            </div>
        </div>

        <!-- Approval Stats -->
        @php
            $approval = is_array($approvalStats) ? $approvalStats : [];
        @endphp
        <div class="card p-4 mb-6">
            <h3 class="font-semibold text-sm mb-4" style="color: var(--text-secondary);">
                <i class="fas fa-check-double mr-2"></i> Approval Status
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="text-center">
                    <div class="text-xl font-bold text-yellow-500">{{ $approval['pending'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Pending</div>
                </div>
                <div class="text-center">
                    <div class="text-xl font-bold text-green-500">{{ $approval['approved'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Approved</div>
                </div>
                <div class="text-center">
                    <div class="text-xl font-bold text-red-500">{{ $approval['rejected'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Rejected</div>
                </div>
                <div class="text-center">
                    <div class="text-xl font-bold text-blue-500">{{ $approval['auto_approved'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Auto-Approved</div>
                </div>
                <div class="text-center">
                    <div class="text-xl font-bold text-gray-500">{{ $approval['expired'] ?? 0 }}</div>
                    <div class="text-xs" style="color: var(--text-secondary);">Expired</div>
                </div>
            </div>
            @if(!empty($approval['average_response_time']))
                <div class="mt-4 pt-4 border-t text-center" style="border-color: var(--border-color);">
                    <span class="text-xs" style="color: var(--text-secondary);">Average Response Time:</span>
                    <span class="text-sm font-semibold ml-2" style="color: var(--text-primary);">
                        {{ round($approval['average_response_time'], 1) }} hours
                    </span>
                </div>
            @endif
        </div>

        <!-- Daily Stats (30 days) — weight column removed -->
        <div class="card p-6 mb-6">
            <h3 class="font-semibold text-sm mb-4" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-2"></i> Last 30 Days
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead style="background-color: var(--bg-secondary);">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Date</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Requests</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Completed</th>
                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Approvals</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="border-color: var(--border-color);">
                        @forelse(array_reverse($dailyStats ?? []) as $day)
                            <tr>
                                <td class="px-3 py-2" style="color: var(--text-primary);">{{ $day['date'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-right" style="color: var(--text-primary);">{{ $day['requests'] ?? 0 }}</td>
                                <td class="px-3 py-2 text-right text-green-500">{{ $day['completed'] ?? 0 }}</td>
                                <td class="px-3 py-2 text-right text-blue-500">{{ $day['approvals'] ?? 0 }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-6 text-center" style="color: var(--text-secondary);">
                                    No daily stats available.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Personnel Performance -->
        <div class="card p-6">
            <h3 class="font-semibold text-sm mb-4" style="color: var(--text-secondary);">
                <i class="fas fa-users mr-2"></i> Personnel Performance
            </h3>
            @if(!empty($personnelPerformance))
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead style="background-color: var(--bg-secondary);">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Name</th>
                                <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Completed</th>
                                <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Total</th>
                                <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Completion Rate</th>
                                <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Avg Time (min)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" style="border-color: var(--border-color);">
                            @foreach($personnelPerformance as $person)
                                <tr>
                                    <td class="px-3 py-2" style="color: var(--text-primary);">{{ $person['name'] ?? 'Unknown' }}</td>
                                    <td class="px-3 py-2 text-right text-green-500 font-medium">{{ $person['completed'] ?? 0 }}</td>
                                    <td class="px-3 py-2 text-right" style="color: var(--text-secondary);">{{ $person['total'] ?? 0 }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <span class="px-2 py-0.5 rounded text-xs font-medium
                                            @if(($person['completion_rate'] ?? 0) >= 80) bg-green-100 text-green-600
                                            @elseif(($person['completion_rate'] ?? 0) >= 50) bg-yellow-100 text-yellow-600
                                            @else bg-red-100 text-red-600 @endif">
                                            {{ $person['completion_rate'] ?? 0 }}%
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right" style="color: var(--text-primary);">
                                        {{ isset($person['avg_time']) ? round($person['avg_time'], 1) : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm" style="color: var(--text-secondary);">No personnel performance data available yet.</p>
            @endif
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .btn-info, .btn-secondary {
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
    .card {
        background-color: var(--card-bg);
        border-radius: 0.75rem;
        border: 1px solid var(--border-color);
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    }
</style>
@endpush