{{-- resources/views/developer/billing/reports.blade.php --}}
@extends('layouts.dev')

@php
    $pageTitle = 'Billing Reports';

    if (!function_exists('formatCurrency')) {
        function formatCurrency($amount, $currency = 'GHS') {
            if (empty($amount)) return 'GH₵0.00';
            if ($currency === 'GHS') {
                return 'GH₵' . number_format((float)$amount, 2);
            }
            return $currency . ' ' . number_format((float)$amount, 2);
        }
    }

    if (!function_exists('getMonthName')) {
        function getMonthName($dateString) {
            try {
                return \Carbon\Carbon::parse($dateString)->format('F Y');
            } catch (\Exception $e) {
                return $dateString;
            }
        }
    }

    // Safety nets — if the controller hands back arrays, wrap them so
    // ->isNotEmpty() / ->sum() work regardless.
    $monthlyBreakdown       = collect($monthlyBreakdown ?? []);
    $paymentMethodBreakdown = collect($paymentMethodBreakdown ?? []);
    $payments               = $payments               ?? collect();
    $agreements             = $agreements             ?? collect();
    $stats                  = $stats                  ?? [];
@endphp

@section('title', $pageTitle)

@section('content')
<div class="grid grid-cols-1 gap-6 mb-6">

    {{-- Header Card --}}
    <div class="card">
        <div class="flex justify-between items-center p-6 flex-wrap gap-3">
            <div class="flex items-center">
                <div class="mr-4">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center border-2"
                         style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; font-weight: 600; border-color: var(--primary);">
                        <i class="fas fa-chart-bar text-xl"></i>
                    </div>
                </div>
                <div>
                    <h2 class="text-xl font-semibold flex items-center" style="color: var(--text-primary);">
                        <i class="fas fa-chart-bar mr-2" style="color: var(--primary);"></i>
                        Billing Reports & Analytics
                    </h2>
                    <div class="text-sm flex items-center mt-1 flex-wrap gap-1" style="color: var(--text-secondary);">
                        <i class="fas fa-info-circle"></i>
                        <span>Generate detailed billing reports and analytics</span>
                        @if($startDate && $endDate)
                        <span class="mx-1">•</span>
                        <i class="fas fa-calendar"></i>
                        <span>{{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('developer.billing.dashboard') }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-medium inline-flex items-center transition-all duration-200 hover:transform hover:-translate-y-0.5"
                   style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary); border: 1px solid rgba(var(--primary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-1"></i> Dashboard
                </a>
                <a href="{{ route('developer.billing.agreements-list') }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-medium inline-flex items-center transition-all duration-200 hover:transform hover:-translate-y-0.5"
                   style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info); border: 1px solid rgba(var(--info-rgb), 0.3);">
                    <i class="fas fa-list mr-1"></i> All Agreements
                </a>
            </div>
        </div>
    </div>

    {{-- Date Range Filter --}}
    <div class="card p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-filter mr-2" style="color: var(--primary);"></i> Select Report Period
        </h3>

        <form method="GET" action="{{ route('developer.billing.reports') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Start Date</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" required
                           class="form-input w-full p-2.5 rounded-lg border"
                           style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">End Date</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" required
                           class="form-input w-full p-2.5 rounded-lg border"
                           style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium" style="color: var(--text-primary);">Report Type</label>
                    <select name="report_type"
                            class="form-select w-full p-2.5 rounded-lg border"
                            style="border-color: var(--border-color); background-color: var(--card-bg); color: var(--text-primary);">
                        <option value="summary"  {{ request('report_type') == 'summary'  ? 'selected' : '' }}>Summary Report</option>
                        <option value="detailed" {{ request('report_type') == 'detailed' ? 'selected' : '' }}>Detailed Report</option>
                        <option value="monthly"  {{ request('report_type') == 'monthly'  ? 'selected' : '' }}>Monthly Breakdown</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end pt-4" style="border-top: 1px solid var(--border-color);">
                <button type="submit"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-chart-bar mr-2"></i> Generate Report
                </button>
            </div>
        </form>
    </div>

    {{-- Summary Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="card stat-card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                     style="background-color: rgba(var(--primary-rgb), 0.1); color: var(--primary);">
                    <i class="fas fa-handshake text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Active Agreements</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        {{ number_format($stats['active_agreements'] ?? 0) }}
                    </p>
                </div>
            </div>
        </div>

        <div class="card stat-card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                     style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                    <i class="fas fa-money-check-alt text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Total Received</p>
                    <p class="text-2xl font-bold" style="color: var(--success);">
                        {{ formatCurrency($stats['total_payment_amount'] ?? 0) }}
                    </p>
                </div>
            </div>
        </div>

        <div class="card stat-card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                     style="background-color: rgba(var(--warning-rgb), 0.1); color: var(--warning);">
                    <i class="fas fa-clock text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Pending Balance</p>
                    <p class="text-2xl font-bold" style="color: var(--warning);">
                        {{ formatCurrency($stats['pending_payments'] ?? 0) }}
                    </p>
                </div>
            </div>
        </div>

        <div class="card stat-card p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                     style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                    <i class="fas fa-percentage text-lg"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" style="color: var(--text-secondary);">Collection Rate</p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        @if(($stats['total_amount_agreed'] ?? 0) > 0)
                            {{ round((($stats['total_amount_received'] ?? 0) / $stats['total_amount_agreed']) * 100, 1) }}%
                        @else
                            0%
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Monthly Breakdown --}}
    @if($monthlyBreakdown->isNotEmpty())
    <div class="card mb-6">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-chart-line mr-2" style="color: var(--primary);"></i> Monthly Revenue Breakdown
            </h3>

            <div class="mb-6" style="height: 16rem;">
                <canvas id="monthlyRevenueChart"></canvas>
            </div>

            <div class="overflow-x-auto">
                <table class="table report-table w-full">
                    <thead>
                        <tr>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Month</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Total Payments</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Number of Payments</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Average Payment</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Trend</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($monthlyBreakdown as $month)
                        <tr>
                            <td class="p-3 border-b" style="border-color: var(--border-color); color: var(--text-primary);">
                                <strong>{{ getMonthName($month['month'] ?? '') }}</strong>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color); color: var(--text-primary);">
                                {{ formatCurrency($month['total_payments'] ?? 0) }}
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color); color: var(--text-secondary);">
                                {{ $month['payment_count'] ?? 0 }}
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color); color: var(--text-primary);">
                                {{ formatCurrency(($month['total_payments'] ?? 0) / max(1, ($month['payment_count'] ?? 1))) }}
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                @php
                                    $trend = $month['trend'] ?? 'stable';
                                    $trendColor = $trend == 'up' ? 'var(--success)' :
                                                 ($trend == 'down' ? 'var(--danger)' : 'var(--text-secondary)');
                                    $trendIcon = $trend == 'up' ? 'fa-arrow-up' :
                                                ($trend == 'down' ? 'fa-arrow-down' : 'fa-minus');
                                @endphp
                                <i class="fas {{ $trendIcon }}" style="color: {{ $trendColor }};"></i>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- Payment Method Breakdown --}}
    @if($paymentMethodBreakdown->isNotEmpty())
    <div class="card mb-6">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-credit-card mr-2" style="color: var(--info);"></i> Payment Method Analysis
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div style="height: 16rem;">
                    <canvas id="paymentMethodChart"></canvas>
                </div>

                <div>
                    <div class="overflow-x-auto">
                        <table class="table report-table w-full">
                            <thead>
                                <tr>
                                    <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Payment Method</th>
                                    <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Payments</th>
                                    <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Total Amount</th>
                                    <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $totalPaymentAmount = $paymentMethodBreakdown->sum('total_amount');
                                @endphp
                                @foreach($paymentMethodBreakdown as $method)
                                <tr>
                                    <td class="p-3 border-b" style="border-color: var(--border-color); color: var(--text-primary);">
                                        <div class="flex items-center">
                                            @php
                                                $methodIcons = [
                                                    'Bank Transfer' => 'fa-university',
                                                    'Mobile Money'  => 'fa-mobile-alt',
                                                    'Cash'          => 'fa-money-bill',
                                                    'Paystack'      => 'fa-credit-card',
                                                ];
                                                $icon = $methodIcons[$method['payment_method'] ?? ''] ?? 'fa-money-bill-wave';
                                            @endphp
                                            <i class="fas {{ $icon }} mr-2" style="color: var(--text-secondary);"></i>
                                            {{ $method['payment_method'] ?? '—' }}
                                        </div>
                                    </td>
                                    <td class="p-3 border-b" style="border-color: var(--border-color); color: var(--text-secondary);">
                                        {{ $method['count'] ?? 0 }}
                                    </td>
                                    <td class="p-3 border-b" style="border-color: var(--border-color); color: var(--text-primary);">
                                        {{ formatCurrency($method['total_amount'] ?? 0) }}
                                    </td>
                                    <td class="p-3 border-b" style="border-color: var(--border-color); color: var(--text-primary);">
                                        @if($totalPaymentAmount > 0)
                                            {{ round((($method['total_amount'] ?? 0) / $totalPaymentAmount) * 100, 1) }}%
                                        @else
                                            0%
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr style="background-color: rgba(var(--primary-rgb), 0.05);">
                                    <td class="p-3 font-bold" style="color: var(--text-primary);">Total</td>
                                    <td class="p-3 font-bold" style="color: var(--text-primary);">{{ $paymentMethodBreakdown->sum('count') }}</td>
                                    <td class="p-3 font-bold" style="color: var(--text-primary);">{{ formatCurrency($totalPaymentAmount) }}</td>
                                    <td class="p-3 font-bold" style="color: var(--text-primary);">100%</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Recent Payments --}}
    @if($payments->isNotEmpty())
    <div class="card mb-6">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-history mr-2" style="color: var(--secondary);"></i>
                Recent Payments ({{ $startDate }} - {{ $endDate }})
            </h3>

            <div class="overflow-x-auto">
                <table class="table report-table w-full">
                    <thead>
                        <tr>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Date</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Agreement</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Super Admin</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Amount</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Method</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Reference</th>
                            <th class="text-left p-3 text-sm font-medium" style="color: var(--text-secondary); background-color: rgba(var(--primary-rgb), 0.05); border-bottom: 1px solid var(--border-color);">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $payment)
                        <tr>
                            <td class="p-3 border-b" style="border-color: var(--border-color); color: var(--text-secondary);">
                                {{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color); color: var(--text-primary);">
                                <span class="font-mono text-sm">{{ $payment->agreement_number ?? '—' }}</span>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color); color: var(--text-secondary);">
                                {{ $payment->super_admin_name ?? 'N/A' }}
                            </td>
                            <td class="p-3 border-b font-medium" style="border-color: var(--border-color); color: var(--text-primary);">
                                {{ formatCurrency($payment->amount_paid, $payment->currency ?? 'GHS') }}
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-info capitalize">
                                    {{ str_replace('_', ' ', $payment->payment_method ?? '—') }}
                                </span>
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color); color: var(--text-secondary);">
                                {{ $payment->transaction_reference ?? 'N/A' }}
                            </td>
                            <td class="p-3 border-b" style="border-color: var(--border-color);">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-success">
                                    {{ ucfirst($payment->status ?? 'confirmed') }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 text-center">
                <a href="{{ route('developer.billing.history', ['start_date' => $startDate, 'end_date' => $endDate]) }}"
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-external-link-alt mr-2"></i> View Full Payment History
                </a>
            </div>
        </div>
    </div>
    @endif

    {{-- Agreement Performance --}}
    @if($agreements->isNotEmpty())
    <div class="card">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
                <i class="fas fa-chart-pie mr-2" style="color: var(--success);"></i> Agreement Performance
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Status Breakdown --}}
                <div>
                    <h4 class="text-md font-semibold mb-4" style="color: var(--text-primary);">Agreement Status</h4>
                    <div class="space-y-3">
                        @php
                            $statusGroups    = $agreements->groupBy('status');
                            $totalAgreements = max(1, $agreements->count());
                        @endphp

                        @foreach($statusGroups as $status => $group)
                        <div class="p-3 rounded-lg" style="border: 1px solid var(--border-color);">
                            <div class="flex justify-between items-center mb-1">
                                <span class="capitalize font-medium" style="color: var(--text-primary);">{{ $status }}</span>
                                <span class="font-bold" style="color: var(--text-primary);">{{ $group->count() }}</span>
                            </div>
                            <div class="h-2 rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.1); overflow: hidden;">
                                <div class="h-full rounded-full transition-all duration-300"
                                     style="width: {{ ($group->count() / $totalAgreements) * 100 }}%;
                                            background-color: {{
                                                $status == 'active' ? 'var(--success)' :
                                                ($status == 'pending' ? 'var(--warning)' :
                                                ($status == 'completed' ? 'var(--info)' : 'var(--secondary)'))
                                            }};">
                                </div>
                            </div>
                            <div class="text-xs mt-1 text-right" style="color: var(--text-secondary);">
                                {{ round(($group->count() / $totalAgreements) * 100, 1) }}%
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Top Performing --}}
                <div>
                    <h4 class="text-md font-semibold mb-4" style="color: var(--text-primary);">Top Performing Agreements</h4>
                    <div class="space-y-3">
                        @foreach($agreements->where('status', 'active')->sortByDesc('amount_received')->take(5) as $agreement)
                        <div class="p-3 rounded-lg" style="border: 1px solid var(--border-color);">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-medium font-mono" style="color: var(--text-primary);">{{ $agreement->agreement_number }}</div>
                                    <div class="text-sm" style="color: var(--text-secondary);">{{ $agreement->superAdmin->name ?? 'N/A' }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="font-medium" style="color: var(--primary);">
                                        {{ formatCurrency($agreement->amount_received, $agreement->currency) }}
                                    </div>
                                    <div class="text-xs" style="color: var(--text-secondary);">
                                        of {{ formatCurrency($agreement->amount, $agreement->currency) }}
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2">
                                <div class="h-2 rounded-full" style="background-color: rgba(var(--secondary-rgb), 0.1); overflow: hidden;">
                                    <div class="h-full rounded-full transition-all duration-300"
                                         style="width: {{ ($agreement->amount_received / max(1, $agreement->amount)) * 100 }}%;
                                                background-color: var(--success);">
                                    </div>
                                </div>
                                <div class="text-xs mt-1 text-right" style="color: var(--text-secondary);">
                                    {{ round(($agreement->amount_received / max(1, $agreement->amount)) * 100, 1) }}% collected
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Export & Actions --}}
    <div class="card mt-6 p-6">
        <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: var(--text-primary);">
            <i class="fas fa-download mr-2" style="color: var(--warning);"></i> Export & Actions
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            {{-- ✅ Export as PDF — hits developer.billing.export-pdf --}}
            <button type="button" onclick="exportReportPdf()"
                    class="flex items-center p-4 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1 text-left"
                    style="background-color: rgba(var(--danger-rgb), 0.05); border: 1px solid rgba(var(--danger-rgb), 0.2);">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                     style="background-color: rgba(var(--danger-rgb), 0.1); color: var(--danger);">
                    <i class="fas fa-file-pdf text-lg"></i>
                </div>
                <div>
                    <p class="font-medium" style="color: var(--text-primary);">Export as PDF</p>
                    <p class="text-xs mt-0.5" style="color: var(--text-secondary);">Full report as a printable PDF</p>
                </div>
            </button>

            {{-- Print --}}
            <button type="button" onclick="printReport()"
                    class="flex items-center p-4 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1 text-left"
                    style="background-color: rgba(var(--info-rgb), 0.05); border: 1px solid rgba(var(--info-rgb), 0.2);">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                     style="background-color: rgba(var(--info-rgb), 0.1); color: var(--info);">
                    <i class="fas fa-print text-lg"></i>
                </div>
                <div>
                    <p class="font-medium" style="color: var(--text-primary);">Print Report</p>
                    <p class="text-xs mt-0.5" style="color: var(--text-secondary);">Print this page</p>
                </div>
            </button>

            {{-- CSV fallback — hits developer.billing.export --}}
            <button type="button" onclick="exportReportCsv('payments')"
                    class="flex items-center p-4 rounded-lg transition-all duration-200 hover:transform hover:-translate-y-1 text-left"
                    style="background-color: rgba(var(--success-rgb), 0.05); border: 1px solid rgba(var(--success-rgb), 0.2);">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3 flex-shrink-0"
                     style="background-color: rgba(var(--success-rgb), 0.1); color: var(--success);">
                    <i class="fas fa-file-csv text-lg"></i>
                </div>
                <div>
                    <p class="font-medium" style="color: var(--text-primary);">Export Data (CSV)</p>
                    <p class="text-xs mt-0.5" style="color: var(--text-secondary);">Raw data for spreadsheet tools</p>
                </div>
            </button>
        </div>

        <div class="mt-6 pt-6 flex justify-between items-center flex-wrap gap-3"
             style="border-top: 1px solid var(--border-color);">
            <div>
                <p class="text-sm" style="color: var(--text-secondary);">
                    <i class="fas fa-info-circle mr-1"></i>
                    Report generated on {{ now()->format('F j, Y \a\t H:i') }}
                </p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="{{ route('developer.billing.dashboard') }}"
                   class="px-4 py-2 rounded-lg font-medium inline-flex items-center"
                   style="background-color: rgba(var(--secondary-rgb), 0.1); color: var(--secondary); border: 1px solid rgba(var(--secondary-rgb), 0.3);">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
                <button type="button" onclick="generateCustomReport()"
                        class="px-4 py-2 rounded-lg font-medium inline-flex items-center text-white btn-primary">
                    <i class="fas fa-cog mr-2"></i> Custom Report
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// ============================================================
//  EXPORT — PDF (primary action)
// ============================================================
//
// Controller: DeveloperBillingController::exportBillingDataPdf
// Route:      GET /developer/billing/export-pdf  → developer.billing.export-pdf
// Params:     start_date, end_date, report_type
//
function exportReportPdf() {
    const params = new URLSearchParams(window.location.search);
    const startDate  = params.get('start_date')  || '{{ $startDate }}';
    const endDate    = params.get('end_date')    || '{{ $endDate }}';
    const reportType = params.get('report_type') || 'summary';

    const url = new URL('{{ route('developer.billing.export-pdf') }}', window.location.origin);
    url.searchParams.set('start_date',  startDate);
    url.searchParams.set('end_date',    endDate);
    url.searchParams.set('report_type', reportType);

    window.location.href = url.toString();
}

// ============================================================
//  EXPORT — CSV (fallback)
// ============================================================
//
// Controller: DeveloperBillingController::exportBillingData
// Route:      GET /developer/billing/export  → developer.billing.export
// Params:     export_type (invoices | payments | agreements),
//             start_date, end_date
//
function exportReportCsv(type) {
    const allowed = ['invoices', 'payments', 'agreements'];
    if (!allowed.includes(type)) type = 'payments';

    const params = new URLSearchParams(window.location.search);
    const startDate = params.get('start_date') || '{{ $startDate }}';
    const endDate   = params.get('end_date')   || '{{ $endDate }}';

    const url = new URL('{{ route('developer.billing.export') }}', window.location.origin);
    url.searchParams.set('export_type', type);
    url.searchParams.set('start_date',  startDate);
    url.searchParams.set('end_date',    endDate);

    window.location.href = url.toString();
}

// ============================================================
//  PRINT
// ============================================================
function printReport() {
    window.print();
}

// ============================================================
//  CUSTOM REPORT (placeholder)
// ============================================================
function generateCustomReport() {
    alert('Custom report feature coming soon!');
}

// ============================================================
//  CHARTS
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    // Monthly Revenue
    const monthlyCtx = document.getElementById('monthlyRevenueChart');
    if (monthlyCtx) {
        const monthlyData = @json($monthlyBreakdown ?? []);

        if (Array.isArray(monthlyData) && monthlyData.length > 0) {
            new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: monthlyData.map(m => m.month),
                    datasets: [{
                        label: 'Monthly Revenue',
                        data: monthlyData.map(m => m.total_payments || 0),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'GH₵' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    // Payment Methods
    const methodCtx = document.getElementById('paymentMethodChart');
    if (methodCtx) {
        const methodData = @json($paymentMethodBreakdown ?? []);

        if (Array.isArray(methodData) && methodData.length > 0) {
            const colors = [
                'rgb(59, 130, 246)',
                'rgb(16, 185, 129)',
                'rgb(245, 158, 11)',
                'rgb(139, 92, 246)',
                'rgb(239, 68, 68)'
            ];

            new Chart(methodCtx, {
                type: 'doughnut',
                data: {
                    labels: methodData.map(m => m.payment_method),
                    datasets: [{
                        data: methodData.map(m => m.total_amount),
                        backgroundColor: colors.slice(0, methodData.length),
                        borderWidth: 2,
                        borderColor: 'var(--card-bg)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { padding: 20, usePointStyle: true }
                        }
                    }
                }
            });
        }
    }
});

// ============================================================
//  UTILITIES
// ============================================================
function formatCurrency(amount) {
    return 'GH₵' + parseFloat(amount || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}
</script>

<style>
/* Report table hover — primary tint, no white flash */
.report-table tbody tr {
    background-color: transparent !important;
    transition: background-color 0.15s ease !important;
}

.report-table tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.04) !important;
}

html.dark .report-table tbody tr:hover,
body.dark .report-table tbody tr:hover,
body[data-theme="dark"] .report-table tbody tr:hover {
    background-color: rgba(var(--primary-rgb), 0.08) !important;
}

.report-table tbody tr:hover > td {
    border-color: var(--border-color) !important;
}

/* Card + stat card */
.card {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06) !important;
}

.stat-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
}

/* Form controls */
.form-input,
.form-select {
    background-color: var(--card-bg) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 8px !important;
    transition: all 0.2s ease !important;
}

.form-input:focus,
.form-select:focus {
    outline: none !important;
    border-color: var(--primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1) !important;
}

/* Badges */
.badge-success {
    background-color: rgba(var(--success-rgb), 0.1) !important;
    color: var(--success) !important;
    border: 1px solid rgba(var(--success-rgb), 0.3) !important;
}

.badge-info {
    background-color: rgba(var(--info-rgb), 0.1) !important;
    color: var(--info) !important;
    border: 1px solid rgba(var(--info-rgb), 0.3) !important;
}

/* Primary button */
.btn-primary {
    background-color: var(--primary) !important;
    color: white !important;
    border: 1px solid var(--primary) !important;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background-color: var(--secondary) !important;
    border-color: var(--secondary) !important;
    transform: translateY(-1px);
}

/* Responsive */
@media (max-width: 768px) {
    .grid.grid-cols-1.md\:grid-cols-4 { grid-template-columns: 1fr; }
    .grid.grid-cols-1.md\:grid-cols-2 { grid-template-columns: 1fr; }
    .grid.grid-cols-1.md\:grid-cols-3 { grid-template-columns: 1fr; }
    .table { font-size: 0.75rem; }
    .table th, .table td { padding: 0.5rem !important; }
    .card .p-6 { padding: 1rem !important; }
    .text-2xl { font-size: 1.25rem !important; }
}

@media print {
    button,
    .btn,
    .modal {
        display: none !important;
    }

    .card {
        border: 1px solid #000;
        break-inside: avoid;
    }

    .stat-card {
        page-break-inside: avoid;
    }

    h1, h2, h3 {
        page-break-after: avoid;
    }

    table {
        page-break-inside: avoid;
    }
}
</style>
@endsection