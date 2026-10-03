{{-- resources/views/superadmin/billing/statistics.blade.php --}}
@extends('layouts.app')

@php
    use Carbon\Carbon;

    $pageTitle = 'Billing Statistics';

    if (!function_exists('statsFormatCurrency')) {
        function statsFormatCurrency($amount, $currency = 'GHS') {
            if (empty($amount)) return 'GH₵0.00';
            if ($currency === 'GHS') {
                return 'GH₵' . number_format((float) $amount, 2);
            }
            return $currency . ' ' . number_format((float) $amount, 2);
        }
    }

    $currentDate = Carbon::now()->format('F j, Y');

    // Safe defaults
    $agreements = $agreements ?? collect();
    $stats      = $stats      ?? [];
    $settings   = $settings   ?? null;

    // Compute derived values the controller may not have provided
    $totalAgreed    = (float) ($stats['total_amount_agreed'] ?? 0);
    $totalPaid      = (float) ($stats['total_amount_paid']   ?? 0);
    $totalOutstanding = (float) ($stats['total_outstanding'] ?? max(0, $totalAgreed - $totalPaid));

    $collectionRate = $totalAgreed > 0
        ? round(($totalPaid / $totalAgreed) * 100, 1)
        : 0;

    // Status breakdown for the pie-style bars
    $statusBreakdown = [
        'Active'     => (int) ($stats['active_agreements']     ?? 0),
        'Pending'    => (int) ($stats['pending_agreements']    ?? 0),
        'Completed'  => (int) ($stats['completed_agreements']  ?? 0),
        'Terminated' => (int) ($stats['terminated_agreements'] ?? 0),
    ];
    $totalForBreakdown = array_sum($statusBreakdown) ?: 1;

    // Monthly trends — aggregate locally from the agreements collection
    $monthlyTrends = $agreements
        ->filter(fn ($a) => $a->created_at)
        ->groupBy(fn ($a) => Carbon::parse($a->created_at)->format('Y-m'))
        ->sortKeys()
        ->take(-6)
        ->map(function ($group, $month) {
            return [
                'month'       => Carbon::parse($month . '-01')->format('M Y'),
                'count'       => $group->count(),
                'total'       => (float) $group->sum('amount'),
                'received'    => (float) $group->sum('amount_received'),
            ];
        })
        ->values();

    $maxMonthlyTotal = $monthlyTrends->max('total') ?: 1;
@endphp

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- ================================================================
     | HEADER
     * ================================================================ --}}
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-3">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-chart-pie text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center flex-wrap gap-2"
                        style="color: var(--text-primary);">
                        <i class="fas fa-chart-pie" style="color: var(--primary);"></i>
                        Billing Statistics
                    </h2>
                    <div class="text-sm flex items-center mt-1 flex-wrap gap-2"
                         style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle"></i>
                        <span>Aggregate view of your billing activity and payment performance</span>
                    </div>
                </div>
            </div>
            <div class="text-sm" style="color: var(--text-secondary);">
                <i class="fas fa-calendar-alt mr-1"></i> {{ $currentDate }}
            </div>
        </div>
    </div>

    {{-- ================================================================
     | BACK NAVIGATION
     * ================================================================ --}}
    <div class="card p-6">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center space-x-3 flex-wrap gap-2">
                <a href="{{ route('superadmin.billing.dashboard') }}"
                   class="inline-flex items-center text-sm font-medium"
                   style="color: var(--primary);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Billing Dashboard
                </a>

                <a href="{{ route('superadmin.billing.agreements-list') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-list-alt mr-1"></i> All Agreements
                </a>

                <a href="{{ route('superadmin.billing.reports') }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-chart-bar mr-1"></i> Detailed Reports
                </a>

                <a href="{{ route('superadmin.billing.export') }}?export_type=agreements"
                   class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium"
                   style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success); border: 1px solid rgba(var(--success-rgb), 0.3);">
                    <i class="fas fa-download mr-1"></i> Export CSV
                </a>
            </div>
        </div>
    </div>

    {{-- ================================================================
     | KEY METRIC CARDS
     * ================================================================ --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- Total Agreed --}}
        <div class="card stat-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                        <i class="fas fa-file-invoice-dollar text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Billed</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ statsFormatCurrency($totalAgreed) }}
                    </p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        Across {{ number_format($stats['total_agreements'] ?? 0) }} agreement(s)
                    </p>
                </div>
            </div>
        </div>

        {{-- Total Paid --}}
        <div class="card stat-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                        <i class="fas fa-check-circle text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Paid</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ statsFormatCurrency($totalPaid) }}
                    </p>
                    <div class="flex items-center gap-2 mt-1">
                        <div class="flex-1 h-2 rounded-full" style="background: rgba(var(--success-rgb), 0.15);">
                            <div class="h-2 rounded-full"
                                 style="width: {{ min(100, max(0, $collectionRate)) }}%; background: var(--success);"></div>
                        </div>
                        <span class="text-xs font-semibold" style="color: var(--success);">
                            {{ $collectionRate }}%
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Outstanding --}}
        <div class="card stat-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                        <i class="fas fa-hourglass-half text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Outstanding</p>
                    <p class="text-2xl font-bold"
                       style="color: {{ $totalOutstanding > 0 ? 'var(--danger)' : 'var(--success)' }};">
                        {{ statsFormatCurrency($totalOutstanding) }}
                    </p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        {{ $totalOutstanding > 0 ? 'Amount still owed' : 'Fully settled' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Signed --}}
        <div class="card stat-card">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center"
                         style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                        <i class="fas fa-file-signature text-lg"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Signed</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ number_format($stats['signed_agreements'] ?? 0) }}
                    </p>
                    <p class="text-xs mt-1" style="color: var(--text-secondary);">
                        {{ number_format($stats['awaiting_signature'] ?? 0) }} awaiting signature
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================================
     | STATUS BREAKDOWN + PERFORMANCE
     * ================================================================ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Agreement Status Breakdown --}}
        <div class="card">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-layer-group mr-2" style="color: var(--primary);"></i>
                    Agreement Status Breakdown
                </h3>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    Distribution of all your agreements by current status
                </p>
            </div>
            <div class="p-6 space-y-4">
                @php
                    $statusColors = [
                        'Active'     => 'var(--success)',
                        'Pending'    => 'var(--warning)',
                        'Completed'  => 'var(--info)',
                        'Terminated' => 'var(--danger)',
                    ];
                @endphp

                @foreach($statusBreakdown as $label => $count)
                    @php
                        $pct = round(($count / $totalForBreakdown) * 100, 1);
                        $color = $statusColors[$label] ?? 'var(--primary)';
                    @endphp
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm font-medium" style="color: var(--text-primary);">
                                {{ $label }}
                            </span>
                            <span class="text-sm font-semibold" style="color: {{ $color }};">
                                {{ $count }} ({{ $pct }}%)
                            </span>
                        </div>
                        <div class="h-2.5 rounded-full" style="background: rgba(0, 0, 0, 0.05);">
                            <div class="h-2.5 rounded-full transition-all duration-500"
                                 style="width: {{ $pct }}%; background: {{ $color }};"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Payment Performance --}}
        <div class="card">
            <div class="p-6 border-b" style="border-color: var(--border-color);">
                <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                    <i class="fas fa-tachometer-alt mr-2" style="color: var(--success);"></i>
                    Payment Performance
                </h3>
                <p class="text-xs mt-1" style="color: var(--text-secondary);">
                    How well you're keeping up with your billing obligations
                </p>
            </div>
            <div class="p-6">
                {{-- Collection Rate Gauge --}}
                <div class="flex items-center justify-center mb-6">
                    <div class="relative" style="width: 160px; height: 160px;">
                        <svg viewBox="0 0 36 36" style="width: 160px; height: 160px; transform: rotate(-90deg);">
                            <circle cx="18" cy="18" r="15.9155"
                                    fill="none" stroke="rgba(0, 0, 0, 0.08)" stroke-width="3"/>
                            <circle cx="18" cy="18" r="15.9155"
                                    fill="none"
                                    stroke="{{ $collectionRate >= 100 ? 'var(--success)' : ($collectionRate >= 50 ? 'var(--warning)' : 'var(--danger)') }}"
                                    stroke-width="3"
                                    stroke-dasharray="{{ min(100, $collectionRate) }} 100"
                                    stroke-linecap="round"/>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-3xl font-bold"
                                  style="color: {{ $collectionRate >= 100 ? 'var(--success)' : ($collectionRate >= 50 ? 'var(--warning)' : 'var(--danger)') }};">
                                {{ $collectionRate }}%
                            </span>
                            <span class="text-xs" style="color: var(--text-secondary);">collected</span>
                        </div>
                    </div>
                </div>

                {{-- Breakdown --}}
                <div class="space-y-3">
                    <div class="flex justify-between items-center p-3 rounded-lg"
                         style="background: rgba(var(--success-rgb), 0.05);">
                        <span class="text-sm" style="color: var(--text-primary);">
                            <i class="fas fa-arrow-down mr-1" style="color: var(--success);"></i>
                            Total received
                        </span>
                        <span class="text-sm font-semibold" style="color: var(--success);">
                            {{ statsFormatCurrency($totalPaid) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center p-3 rounded-lg"
                         style="background: rgba(var(--danger-rgb), 0.05);">
                        <span class="text-sm" style="color: var(--text-primary);">
                            <i class="fas fa-arrow-up mr-1" style="color: var(--danger);"></i>
                            Still owed
                        </span>
                        <span class="text-sm font-semibold" style="color: var(--danger);">
                            {{ statsFormatCurrency($totalOutstanding) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center p-3 rounded-lg"
                         style="background: rgba(var(--primary-rgb), 0.05);">
                        <span class="text-sm" style="color: var(--text-primary);">
                            <i class="fas fa-balance-scale mr-1" style="color: var(--primary);"></i>
                            Total obligation
                        </span>
                        <span class="text-sm font-semibold" style="color: var(--primary);">
                            {{ statsFormatCurrency($totalAgreed) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================================
     | MONTHLY TRENDS
     * ================================================================ --}}
    @if($monthlyTrends->count() > 0)
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-chart-bar mr-2" style="color: var(--info);"></i>
                Monthly Trends
                <span class="text-xs font-normal ml-2" style="color: var(--text-secondary);">
                    Last 6 months of activity
                </span>
            </h3>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th class="text-left p-3 text-sm font-medium"
                                style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">
                                Month
                            </th>
                            <th class="text-left p-3 text-sm font-medium"
                                style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">
                                Agreements
                            </th>
                            <th class="text-left p-3 text-sm font-medium"
                                style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">
                                Total Billed
                            </th>
                            <th class="text-left p-3 text-sm font-medium"
                                style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">
                                Received
                            </th>
                            <th class="text-left p-3 text-sm font-medium"
                                style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">
                                Progress
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($monthlyTrends as $trend)
                        @php
                            $monthPct = $trend['total'] > 0
                                ? round(($trend['received'] / $trend['total']) * 100, 1)
                                : 0;
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <span class="font-medium" style="color: var(--text-primary);">
                                    {{ $trend['month'] }}
                                </span>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-primary">
                                    <i class="fas fa-file-contract mr-1" style="font-size: 0.625rem;"></i>
                                    {{ $trend['count'] }}
                                </span>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <span class="font-semibold" style="color: var(--text-primary);">
                                    {{ statsFormatCurrency($trend['total']) }}
                                </span>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <span class="font-semibold" style="color: var(--success);">
                                    {{ statsFormatCurrency($trend['received']) }}
                                </span>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 h-2 rounded-full"
                                         style="background: rgba(0, 0, 0, 0.05); max-width: 120px;">
                                        <div class="h-2 rounded-full"
                                             style="width: {{ min(100, $monthPct) }}%;
                                                    background: {{ $monthPct >= 100 ? 'var(--success)' : ($monthPct >= 50 ? 'var(--warning)' : 'var(--danger)') }};"></div>
                                    </div>
                                    <span class="text-xs font-medium" style="color: var(--text-secondary);">
                                        {{ $monthPct }}%
                                    </span>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- ================================================================
     | ALL AGREEMENTS TABLE
     * ================================================================ --}}
    <div class="card">
        <div class="flex justify-between items-center p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-list mr-2" style="color: var(--primary);"></i>
                All Agreements
            </h3>
            <span class="text-sm" style="color: var(--text-secondary);">
                {{ $agreements->count() }} total
            </span>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Agreement #</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Status</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Amount</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Received</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Frequency</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($agreements as $agreement)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <div class="font-mono text-sm font-medium flex items-center"
                                     style="color: var(--text-primary);">
                                    @if($agreement->is_primary_for_billing ?? false)
                                    <i class="fas fa-star text-xs mr-1"
                                       style="color: var(--success);"
                                       title="Primary Billing Agreement"></i>
                                    @endif
                                    {{ $agreement->agreement_number }}
                                </div>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                @php
                                    $statusClass = match($agreement->status) {
                                        'active'     => 'badge-success',
                                        'pending'    => 'badge-warning',
                                        'completed'  => 'badge-info',
                                        'terminated' => 'badge-danger',
                                        'rejected'   => 'badge-danger',
                                        default      => 'badge-secondary',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $statusClass }}">
                                    <i class="fas fa-circle mr-1" style="font-size: 0.5rem;"></i>
                                    {{ ucfirst($agreement->status) }}
                                </span>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <span class="font-semibold" style="color: var(--text-primary);">
                                    {{ statsFormatCurrency($agreement->amount, $agreement->currency) }}
                                </span>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <span class="font-semibold" style="color: var(--success);">
                                    {{ statsFormatCurrency($agreement->amount_received, $agreement->currency) }}
                                </span>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <span class="text-sm" style="color: var(--text-secondary);">
                                    {{ ucfirst($agreement->billing_frequency ?? 'Monthly') }}
                                </span>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <span class="text-sm" style="color: var(--text-secondary);">
                                    {{ $agreement->created_at
                                        ? Carbon::parse($agreement->created_at)->format('M d, Y')
                                        : '—' }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center" style="border-color: var(--border-color);">
                                <i class="fas fa-inbox text-3xl mb-2" style="color: var(--text-secondary);"></i>
                                <p style="color: var(--text-secondary);">No agreements yet</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ================================================================
     | QUICK ACTIONS
     * ================================================================ --}}
    <div class="card">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
            <h3 class="text-lg font-semibold flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-bolt mr-2" style="color: var(--primary);"></i>
                Quick Actions
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="{{ route('superadmin.billing.dashboard') }}"
                   class="card h-full transition-all duration-200 hover:shadow-lg">
                    <div class="p-6 flex flex-col items-center text-center">
                        <div class="w-14 h-14 rounded-full flex items-center justify-center mb-3"
                             style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                            <i class="fas fa-tachometer-alt text-lg"></i>
                        </div>
                        <h5 class="font-semibold mb-1" style="color: var(--text-primary);">Dashboard</h5>
                        <p class="text-xs" style="color: var(--text-secondary);">Back to billing overview</p>
                    </div>
                </a>

                <a href="{{ route('superadmin.billing.reports') }}"
                   class="card h-full transition-all duration-200 hover:shadow-lg">
                    <div class="p-6 flex flex-col items-center text-center">
                        <div class="w-14 h-14 rounded-full flex items-center justify-center mb-3"
                             style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                            <i class="fas fa-chart-line text-lg"></i>
                        </div>
                        <h5 class="font-semibold mb-1" style="color: var(--text-primary);">Detailed Reports</h5>
                        <p class="text-xs" style="color: var(--text-secondary);">Time-range billing reports</p>
                    </div>
                </a>

                <a href="{{ route('superadmin.billing.export') }}?export_type=agreements"
                   class="card h-full transition-all duration-200 hover:shadow-lg">
                    <div class="p-6 flex flex-col items-center text-center">
                        <div class="w-14 h-14 rounded-full flex items-center justify-center mb-3"
                             style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                            <i class="fas fa-file-export text-lg"></i>
                        </div>
                        <h5 class="font-semibold mb-1" style="color: var(--text-primary);">Export Data</h5>
                        <p class="text-xs" style="color: var(--text-secondary);">Download billing data as CSV</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Counter animation for stat numbers
    document.addEventListener('DOMContentLoaded', function () {
        const counters = document.querySelectorAll('.text-2xl.font-bold');
        counters.forEach(counter => {
            const text = counter.textContent.trim();
            const isCurrency = text.includes('GH₵');
            const numericText = text.replace('GH₵', '').replace(/,/g, '').trim();

            if (/^\d+(\.\d+)?$/.test(numericText)) {
                const target = parseFloat(numericText);
                if (isNaN(target) || target === 0) return;

                const duration = 1200;
                const startTime = performance.now();
                const isInt = !numericText.includes('.');

                const animate = (now) => {
                    const elapsed = now - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    const current = target * progress;

                    counter.textContent = isCurrency
                        ? 'GH₵' + current.toLocaleString(undefined, {
                            minimumFractionDigits: isInt ? 0 : 2,
                            maximumFractionDigits: isInt ? 0 : 2,
                        })
                        : (isInt
                            ? Math.floor(current).toLocaleString()
                            : current.toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            }));

                    if (progress < 1) requestAnimationFrame(animate);
                };

                requestAnimationFrame(animate);
            }
        });

        // Animate the horizontal progress bars from 0 to target
        document.querySelectorAll('.h-2.5.rounded-full').forEach(bar => {
            const targetWidth = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => { bar.style.width = targetWidth; }, 100);
        });
    });
</script>

<style>
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

.stat-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease !important;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
}

.badge-success {
    background-color: rgba(var(--success-rgb), 0.1) !important;
    color: var(--success) !important;
    border: 1px solid rgba(var(--success-rgb), 0.3) !important;
}

.badge-warning {
    background-color: rgba(var(--warning-rgb), 0.1) !important;
    color: var(--warning) !important;
    border: 1px solid rgba(var(--warning-rgb), 0.3) !important;
}

.badge-danger {
    background-color: rgba(var(--danger-rgb), 0.1) !important;
    color: var(--danger) !important;
    border: 1px solid rgba(var(--danger-rgb), 0.3) !important;
}

.badge-info {
    background-color: rgba(var(--info-rgb), 0.1) !important;
    color: var(--info) !important;
    border: 1px solid rgba(var(--info-rgb), 0.3) !important;
}

.badge-primary {
    background-color: rgba(var(--primary-rgb), 0.1) !important;
    color: var(--primary) !important;
    border: 1px solid rgba(var(--primary-rgb), 0.3) !important;
}

.badge-secondary {
    background-color: rgba(var(--secondary-rgb), 0.1) !important;
    color: var(--secondary) !important;
    border: 1px solid rgba(var(--secondary-rgb), 0.3) !important;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table tr:hover {
    background-color: rgba(var(--primary-rgb), 0.02) !important;
}

.card.h-full {
    text-decoration: none !important;
    color: inherit !important;
}

@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-2.lg\:grid-cols-4 { grid-template-columns: 1fr !important; }
    .grid.grid-cols-1.lg\:grid-cols-2 { grid-template-columns: 1fr !important; }
    .table { font-size: 0.75rem; }
    .table th, .table td { padding: 0.5rem !important; }
    .text-2xl { font-size: 1.25rem !important; }
}
</style>
@endsection