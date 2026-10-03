{{-- resources/views/developer/billing/exports/report-pdf.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Billing Report - {{ $startDate }} to {{ $endDate }}</title>
    <style>
        @page { margin: 30px 25px; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #1f2937;
            line-height: 1.4;
        }

        h1 {
            font-size: 18px;
            margin: 0 0 4px 0;
            color: #111827;
        }

        h2 {
            font-size: 13px;
            margin: 18px 0 8px 0;
            color: #111827;
            border-bottom: 1.5px solid #374151;
            padding-bottom: 4px;
        }

        .muted { color: #6b7280; }
        .small { font-size: 9px; }

        .header {
            text-align: center;
            padding-bottom: 14px;
            border-bottom: 2px solid #111827;
            margin-bottom: 18px;
        }

        .header .company { font-size: 13px; color: #4b5563; margin-top: 4px; }
        .header .meta { font-size: 9px; color: #6b7280; margin-top: 6px; }

        .kpi-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin-bottom: 18px;
        }
        .kpi-grid td {
            width: 25%;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 8px 10px;
            vertical-align: top;
        }
        .kpi-label { font-size: 8px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.4px; }
        .kpi-value { font-size: 14px; font-weight: bold; color: #111827; margin-top: 3px; }
        .kpi-value.green  { color: #059669; }
        .kpi-value.yellow { color: #d97706; }
        .kpi-value.blue   { color: #2563eb; }

        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        table.data th {
            background-color: #f3f4f6;
            font-size: 9px;
            text-align: left;
            padding: 6px 6px;
            border-bottom: 1.5px solid #9ca3af;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        table.data td {
            font-size: 9px;
            padding: 5px 6px;
            border-bottom: 1px solid #e5e7eb;
            color: #111827;
        }
        table.data tfoot td {
            font-weight: bold;
            background-color: #f9fafb;
            border-top: 1.5px solid #374151;
        }
        .right { text-align: right; }
        .center { text-align: center; }

        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-success { background-color: #d1fae5; color: #065f46; }
        .badge-warning { background-color: #fef3c7; color: #92400e; }
        .badge-danger  { background-color: #fee2e2; color: #991b1b; }
        .badge-info    { background-color: #dbeafe; color: #1e40af; }
        .badge-gray    { background-color: #e5e7eb; color: #374151; }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
        }

        .page-break { page-break-before: always; }

        .empty {
            padding: 14px;
            text-align: center;
            color: #9ca3af;
            font-style: italic;
            background-color: #f9fafb;
            border: 1px dashed #d1d5db;
            border-radius: 4px;
        }
    </style>
</head>
<body>

    {{-- ─── Header ──────────────────────────────────────── --}}
    <div class="header">
        <h1>Billing Report</h1>
        <div class="company">{{ $settings->developer_name ?? config('app.name') }}</div>
        <div class="meta">
            Period: <strong>{{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }}</strong>
            &nbsp;to&nbsp;
            <strong>{{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}</strong>
            &nbsp;•&nbsp;
            Report Type: <strong>{{ ucfirst($reportType) }}</strong>
            &nbsp;•&nbsp;
            Generated: {{ $generatedAt->format('M d, Y H:i') }}
        </div>
    </div>

    {{-- ─── KPI Grid ───────────────────────────────────── --}}
    <table class="kpi-grid">
        <tr>
            <td>
                <div class="kpi-label">Active Agreements</div>
                <div class="kpi-value blue">{{ number_format($stats['active_agreements']) }}</div>
            </td>
            <td>
                <div class="kpi-label">Total Received</div>
                <div class="kpi-value green">
                    {{ $settings->billing_currency ?? 'GHS' }} {{ number_format($stats['total_amount_received'], 2) }}
                </div>
            </td>
            <td>
                <div class="kpi-label">Pending Balance</div>
                <div class="kpi-value yellow">
                    {{ $settings->billing_currency ?? 'GHS' }} {{ number_format($stats['pending_payments'], 2) }}
                </div>
            </td>
            <td>
                <div class="kpi-label">Collection Rate</div>
                <div class="kpi-value blue">{{ $stats['collection_rate'] }}%</div>
            </td>
        </tr>
    </table>

    {{-- ─── Monthly Breakdown ─────────────────────────── --}}
    <h2>Monthly Revenue Breakdown</h2>
    @if($monthlyBreakdown->isNotEmpty())
        <table class="data">
            <thead>
                <tr>
                    <th>Month</th>
                    <th class="right">Total Payments</th>
                    <th class="right">Avg. Payment</th>
                </tr>
            </thead>
            <tbody>
                @foreach($monthlyBreakdown as $month)
                <tr>
                    <td>{{ $month['month'] ?? '—' }}</td>
                    <td class="right">
                        {{ $settings->billing_currency ?? 'GHS' }} {{ number_format($month['total_payments'] ?? 0, 2) }}
                    </td>
                    <td class="right">
                        {{ $settings->billing_currency ?? 'GHS' }}
                        {{ number_format(($month['total_payments'] ?? 0) / max(1, ($month['payment_count'] ?? 1)), 2) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td>Total</td>
                    <td class="right">
                        {{ $settings->billing_currency ?? 'GHS' }} {{ number_format($monthlyBreakdown->sum('total_payments'), 2) }}
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @else
        <div class="empty">No monthly data available for this period.</div>
    @endif

    {{-- ─── Payment Method Breakdown ──────────────────── --}}
    <h2>Payment Method Analysis</h2>
    @if($paymentMethodBreakdown->isNotEmpty())
        @php $totalPaymentAmount = $paymentMethodBreakdown->sum('total_amount'); @endphp
        <table class="data">
            <thead>
                <tr>
                    <th>Payment Method</th>
                    <th class="right">Payments</th>
                    <th class="right">Total Amount</th>
                    <th class="right">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach($paymentMethodBreakdown as $method)
                <tr>
                    <td>{{ $method['payment_method'] ?? '—' }}</td>
                    <td class="right">{{ $method['count'] ?? 0 }}</td>
                    <td class="right">
                        {{ $settings->billing_currency ?? 'GHS' }} {{ number_format($method['total_amount'] ?? 0, 2) }}
                    </td>
                    <td class="right">
                        {{ $totalPaymentAmount > 0 ? round((($method['total_amount'] ?? 0) / $totalPaymentAmount) * 100, 1) : 0 }}%
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td>Total</td>
                    <td class="right">{{ $paymentMethodBreakdown->sum('count') }}</td>
                    <td class="right">
                        {{ $settings->billing_currency ?? 'GHS' }} {{ number_format($totalPaymentAmount, 2) }}
                    </td>
                    <td class="right">100%</td>
                </tr>
            </tfoot>
        </table>
    @else
        <div class="empty">No payment method data available for this period.</div>
    @endif

    {{-- ─── Payments ──────────────────────────────────── --}}
    <h2>Payments Received ({{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} – {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }})</h2>
    @if($payments->isNotEmpty())
        <table class="data">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Agreement</th>
                    <th>Super Admin</th>
                    <th class="right">Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payments as $payment)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}</td>
                    <td>{{ $payment->agreement_number ?? '—' }}</td>
                    <td>{{ $payment->super_admin_name ?? '—' }}</td>
                    <td class="right">
                        {{ $payment->currency ?? ($settings->billing_currency ?? 'GHS') }}
                        {{ number_format($payment->amount_paid, 2) }}
                    </td>
                    <td>{{ ucfirst(str_replace('_', ' ', $payment->payment_method ?? '—')) }}</td>
                    <td>{{ $payment->transaction_reference ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">Total Payments</td>
                    <td class="right">
                        {{ $settings->billing_currency ?? 'GHS' }} {{ number_format($payments->sum('amount_paid'), 2) }}
                    </td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    @else
        <div class="empty">No payments recorded for this period.</div>
    @endif

    {{-- ─── Agreements ────────────────────────────────── --}}
    <div class="page-break"></div>

    <h2>Agreements ({{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} – {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }})</h2>
    @if($agreements->isNotEmpty())
        <table class="data">
            <thead>
                <tr>
                    <th>Agreement #</th>
                    <th>Super Admin</th>
                    <th class="right">Amount</th>
                    <th class="right">Received</th>
                    <th>Status</th>
                    <th>Payment</th>
                </tr>
            </thead>
            <tbody>
                @foreach($agreements as $agreement)
                <tr>
                    <td>
                        @if($agreement->is_primary_for_billing ?? false)★ @endif
                        {{ $agreement->agreement_number }}
                    </td>
                    <td>{{ $agreement->superAdmin->name ?? '—' }}</td>
                    <td class="right">
                        {{ $agreement->currency ?? 'GHS' }} {{ number_format($agreement->amount, 2) }}
                    </td>
                    <td class="right">
                        {{ $agreement->currency ?? 'GHS' }} {{ number_format($agreement->amount_received, 2) }}
                    </td>
                    <td>
                        @php
                            $statusClass = match($agreement->status) {
                                'active'     => 'badge-success',
                                'pending'    => 'badge-warning',
                                'completed'  => 'badge-info',
                                'terminated' => 'badge-danger',
                                default      => 'badge-gray',
                            };
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ ucfirst($agreement->status) }}</span>
                    </td>
                    <td>{{ ucfirst($agreement->payment_status ?? 'unpaid') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2">Totals</td>
                    <td class="right">
                        {{ $settings->billing_currency ?? 'GHS' }} {{ number_format($agreements->sum('amount'), 2) }}
                    </td>
                    <td class="right">
                        {{ $settings->billing_currency ?? 'GHS' }} {{ number_format($agreements->sum('amount_received'), 2) }}
                    </td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    @else
        <div class="empty">No agreements recorded for this period.</div>
    @endif

    {{-- ─── Footer ────────────────────────────────────── --}}
    <div class="footer">
        Generated on {{ $generatedAt->format('F j, Y \a\t H:i') }}
        @if($generatedBy) by {{ $generatedBy->name ?? $generatedBy->email }} @endif
        &nbsp;•&nbsp; {{ config('app.name') }}
    </div>

</body>
</html>