{{-- resources/views/sanitation/reports.blade.php --}}

@php
    $user = auth()->user();
    $isAdmin = $user->isAdmin() || $user->isSuperAdmin();
    $layout = $isAdmin ? 'layouts.app' : 'layouts.san';

    // Normalise values so the template never breaks
    $report     = is_array($reportData) ? $reportData : [];
    $type       = $type ?? ($report['type'] ?? 'daily');
    $date       = $date ?? ($report['date'] ?? now()->toDateString());
    $data       = is_array($report['data'] ?? null) ? $report['data'] : [];
    $generatedAt = $report['generated_at'] ?? now()->toISOString();
@endphp

@extends($layout)

@section('title', 'Sanitation Reports')

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
                    <i class="fas fa-file-alt mr-2" style="color: var(--primary);"></i>
                    Sanitation Reports
                </h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Generate and view collection reports
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('sanitation.statistics') }}" class="btn-info">
                    <i class="fas fa-chart-line mr-2"></i> Statistics
                </a>
                <a href="{{ route('sanitation.dashboard') }}" class="btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- Report Controls -->
        <div class="card p-4 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Report Type</label>
                    <select name="type" class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                        <option value="daily"   {{ $type === 'daily'   ? 'selected' : '' }}>Daily</option>
                        <option value="weekly"  {{ $type === 'weekly'  ? 'selected' : '' }}>Weekly</option>
                        <option value="monthly" {{ $type === 'monthly' ? 'selected' : '' }}>Monthly</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Date</label>
                    <input type="date" name="date" value="{{ $date }}"
                           class="w-full p-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           style="background-color: var(--bg-secondary); color: var(--text-primary); border-color: var(--border-color);">
                </div>
                <div class="md:col-span-2 flex items-end gap-2">
                    <button type="submit" class="btn-primary flex-1">
                        <i class="fas fa-sync-alt mr-2"></i> Generate
                    </button>
                    <a href="{{ route('sanitation.reports') }}" class="btn-secondary flex-1 text-center">
                        <i class="fas fa-undo mr-2"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Report Meta -->
        <div class="card p-4 mb-6">
            <div class="flex flex-wrap justify-between gap-4 text-sm">
                <div>
                    <span style="color: var(--text-secondary);">Report Type:</span>
                    <span class="font-medium ml-1" style="color: var(--text-primary);">{{ ucfirst($type) }}</span>
                </div>
                <div>
                    <span style="color: var(--text-secondary);">Reference Date:</span>
                    <span class="font-medium ml-1" style="color: var(--text-primary);">
                        {{ \Carbon\Carbon::parse($date)->format('M d, Y') }}
                    </span>
                </div>
                @if(!empty($data['period']))
                    <div>
                        <span style="color: var(--text-secondary);">Period:</span>
                        <span class="font-medium ml-1" style="color: var(--text-primary);">
                            {{ $data['period']['start'] ?? '—' }} → {{ $data['period']['end'] ?? '—' }}
                        </span>
                    </div>
                @endif
                <div>
                    <span style="color: var(--text-secondary);">Generated:</span>
                    <span class="font-medium ml-1" style="color: var(--text-primary);">
                        {{ \Carbon\Carbon::parse($generatedAt)->format('M d, Y g:i A') }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold" style="color: var(--text-primary);">{{ $data['total_requests'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Total Requests</div>
            </div>
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold text-green-500">{{ $data['completed'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Completed</div>
            </div>
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold text-yellow-500">{{ $data['pending'] ?? 0 }}</div>
                <div class="text-xs" style="color: var(--text-secondary);">Pending</div>
            </div>
            <div class="card p-4 text-center">
                <div class="text-2xl font-bold text-blue-500">
                    {{ $data['total_collections'] ?? ($data['completed'] ?? 0) }}
                </div>
                <div class="text-xs" style="color: var(--text-secondary);">Collections</div>
            </div>
        </div>

        <!-- Priority Breakdown (daily report) -->
        @if(!empty($data['by_priority']))
            <div class="card p-6 mb-6">
                <h3 class="font-semibold text-sm mb-4" style="color: var(--text-secondary);">
                    <i class="fas fa-flag mr-2"></i> By Priority
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @foreach($data['by_priority'] as $priority => $count)
                        <div class="text-center">
                            <div class="text-xl font-bold
                                @if($priority === 'emergency') text-red-500
                                @elseif($priority === 'high') text-orange-500
                                @elseif($priority === 'medium') text-yellow-500
                                @else text-blue-500 @endif">
                                {{ $count }}
                            </div>
                            <div class="text-xs" style="color: var(--text-secondary);">{{ ucfirst($priority) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Waste Type Breakdown (daily report) -->
        @if(!empty($data['by_waste_type']))
            <div class="card p-6 mb-6">
                <h3 class="font-semibold text-sm mb-4" style="color: var(--text-secondary);">
                    <i class="fas fa-trash mr-2"></i> By Waste Type
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    @foreach($data['by_waste_type'] as $wasteType => $count)
                        <div class="text-center">
                            <div class="text-xl font-bold" style="color: var(--text-primary);">{{ $count }}</div>
                            <div class="text-xs" style="color: var(--text-secondary);">{{ ucfirst($wasteType) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Approval Summary (all reports) -->
        @php
            $approval = $data['approval_summary'] ?? ($data['approvals'] ?? []);
        @endphp
        @if(!empty($approval))
            <div class="card p-6 mb-6">
                <h3 class="font-semibold text-sm mb-4" style="color: var(--text-secondary);">
                    <i class="fas fa-check-double mr-2"></i> Approval Summary
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
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
                        <div class="text-xl font-bold text-yellow-500">{{ $approval['pending'] ?? 0 }}</div>
                        <div class="text-xs" style="color: var(--text-secondary);">Pending</div>
                    </div>
                </div>
                @if(!empty($approval['average_response_time_hours']))
                    <div class="mt-4 pt-4 border-t text-center" style="border-color: var(--border-color);">
                        <span class="text-xs" style="color: var(--text-secondary);">Avg Response Time:</span>
                        <span class="text-sm font-semibold ml-2" style="color: var(--text-primary);">
                            {{ round($approval['average_response_time_hours'], 1) }} hours
                        </span>
                    </div>
                @endif
            </div>
        @endif

        <!-- Daily Breakdown (weekly / monthly reports) -->
        @if(!empty($data['daily_breakdown']))
            <div class="card p-6 mb-6">
                <h3 class="font-semibold text-sm mb-4" style="color: var(--text-secondary);">
                    <i class="fas fa-calendar-day mr-2"></i> Daily Breakdown
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
                            @foreach($data['daily_breakdown'] as $day)
                                <tr>
                                    <td class="px-3 py-2" style="color: var(--text-primary);">{{ $day['date'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right" style="color: var(--text-primary);">{{ $day['requests'] ?? 0 }}</td>
                                    <td class="px-3 py-2 text-right text-green-500">{{ $day['completed'] ?? 0 }}</td>
                                    <td class="px-3 py-2 text-right text-blue-500">{{ $day['approvals'] ?? 0 }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Top Personnel (monthly report) -->
        @if(!empty($data['top_personnel']))
            <div class="card p-6">
                <h3 class="font-semibold text-sm mb-4" style="color: var(--text-secondary);">
                    <i class="fas fa-trophy mr-2"></i> Top Personnel
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead style="background-color: var(--bg-secondary);">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Name</th>
                                <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Completed</th>
                                <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Total</th>
                                <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wider" style="color: var(--text-secondary);">Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" style="border-color: var(--border-color);">
                            @foreach($data['top_personnel'] as $person)
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
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Empty state -->
        @if(empty($data))
            <div class="card p-8 text-center" style="color: var(--text-secondary);">
                <i class="fas fa-file-alt text-4xl mb-3 block" style="color: var(--text-secondary); opacity: 0.3;"></i>
                <p>No report data available for the selected type and date.</p>
            </div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
    .btn-primary, .btn-info, .btn-secondary {
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