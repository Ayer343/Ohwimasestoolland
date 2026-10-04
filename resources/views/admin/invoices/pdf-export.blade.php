{{-- resources/views/admin/invoices/pdf-export.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice Report — {{ now()->format('F Y') }}</title>
    <style>
        /* ============================================================
           RESET
           ============================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            margin: 90px 40px 70px 40px;
        }

        body {
            font-family: 'DejaVu Sans', 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            line-height: 1.45;
            color: #1f2937;
        }

        /* ============================================================
           HEADER / FOOTER (rendered on every page via position: fixed)
           ============================================================ */
        .pdf-header {
            position: fixed;
            top: -70px;
            left: 0;
            right: 0;
            height: 60px;
            border-bottom: 2px solid #4F46E5;
        }

        .pdf-header table {
            width: 100%;
            border-collapse: collapse;
        }

        .pdf-header .logo-cell {
            width: 70px;
            vertical-align: middle;
        }

        .pdf-header .logo-cell img {
            max-height: 50px;
            max-width: 60px;
        }

        .pdf-header .logo-fallback {
            width: 46px;
            height: 46px;
            background: #4F46E5;
            color: #fff;
            font-size: 20px;
            font-weight: bold;
            text-align: center;
            line-height: 46px;
            border-radius: 6px;
        }

        .pdf-header .title-cell {
            vertical-align: middle;
            padding-left: 12px;
        }

        .pdf-header .company-name {
            font-size: 14px;
            font-weight: bold;
            color: #111827;
            line-height: 1.2;
        }

        .pdf-header .company-meta {
            font-size: 8px;
            color: #6b7280;
            margin-top: 2px;
        }

        .pdf-header .report-cell {
            vertical-align: middle;
            text-align: right;
        }

        .pdf-header .report-label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #6b7280;
        }

        .pdf-header .report-title {
            font-size: 12px;
            font-weight: bold;
            color: #4F46E5;
        }

        .pdf-header .report-generated {
            font-size: 8px;
            color: #9ca3af;
            margin-top: 2px;
        }

        .pdf-footer {
            position: fixed;
            bottom: -50px;
            left: 0;
            right: 0;
            height: 40px;
            font-size: 8px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
        }

        .pdf-footer table {
            width: 100%;
            border-collapse: collapse;
        }

        .pdf-footer td {
            vertical-align: middle;
        }

        .pdf-footer .page-number:after {
            content: counter(page) ' / ' counter(pages);
        }

        /* ============================================================
           REPORT BODY
           ============================================================ */
        .report-section {
            margin-bottom: 18px;
        }

        .section-title {
            font-size: 10px;
            font-weight: bold;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 10px;
        }

        /* KPI STRIP */
        .kpi-strip {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin-bottom: 16px;
        }

        .kpi-strip td {
            width: 16.66%;
            padding: 10px;
            background: #f9fafb;
            border-left: 3px solid #4F46E5;
            border-radius: 4px;
            vertical-align: top;
        }

        .kpi-strip td.kpi-success { border-left-color: #10b981; }
        .kpi-strip td.kpi-warning { border-left-color: #f59e0b; }
        .kpi-strip td.kpi-danger  { border-left-color: #ef4444; }
        .kpi-strip td.kpi-info    { border-left-color: #0ea5e9; }

        .kpi-label {
            font-size: 7px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .kpi-value {
            font-size: 15px;
            font-weight: bold;
            color: #111827;
            line-height: 1.1;
        }

        .kpi-sub {
            font-size: 7px;
            color: #9ca3af;
            margin-top: 3px;
        }

        /* FILTERS */
        .filters-block {
            background: #f3f4f6;
            border-radius: 4px;
            padding: 10px 12px;
            margin-bottom: 16px;
        }

        .filters-block .filters-title {
            font-size: 9px;
            font-weight: bold;
            color: #374151;
            margin-bottom: 6px;
        }

        .filters-block table {
            width: 100%;
            border-collapse: collapse;
        }

        .filters-block td {
            padding: 2px 12px 2px 0;
            font-size: 9px;
            color: #4b5563;
            vertical-align: top;
        }

        .filter-chip {
            display: inline-block;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 3px;
            padding: 2px 6px;
            font-size: 8px;
            color: #374151;
        }

        .filter-chip .label {
            font-weight: bold;
            color: #6b7280;
            margin-right: 4px;
        }

        /* ============================================================
           DATA TABLE
           ============================================================ */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
        }

        .data-table thead {
            display: table-header-group;
        }

        .data-table th {
            background: #4F46E5;
            color: #ffffff;
            text-align: left;
            padding: 7px 6px;
            font-weight: bold;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-bottom: 1px solid #4338ca;
        }

        .data-table td {
            padding: 7px 6px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
            color: #374151;
        }

        .data-table tbody tr:nth-child(even) td {
            background: #fafbfc;
        }

        .data-table tbody tr {
            page-break-inside: avoid;
        }

        .data-table .text-right { text-align: right; }
        .data-table .text-center { text-align: center; }
        .data-table .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 8px; }
        .data-table .muted { color: #9ca3af; }
        .data-table .amount { color: #059669; font-weight: bold; }
        .data-table .penalty { color: #dc2626; }
        .data-table .sub { font-size: 7.5px; color: #9ca3af; display: block; margin-top: 1px; }

        /* BADGES */
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .badge-paid         { background: #d1fae5; color: #065f46; }
        .badge-pending      { background: #fef3c7; color: #92400e; }
        .badge-overdue      { background: #fee2e2; color: #991b1b; }
        .badge-processing   { background: #dbeafe; color: #1e40af; }
        .badge-partial      { background: #ede9fe; color: #5b21b6; }
        .badge-cancelled    { background: #e5e7eb; color: #374151; }
        .badge-consolidated { background: #c7d2fe; color: #3730a3; }

        .tag {
            display: inline-block;
            padding: 1.5px 5px;
            border-radius: 3px;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .tag-bulk    { background: #e0e7ff; color: #3730a3; }
        .tag-child   { background: #ede9fe; color: #5b21b6; }
        .tag-regular { background: #f3f4f6; color: #4b5563; }

        /* ============================================================
           TOTALS BLOCK
           ============================================================ */
        .totals-block {
            margin-top: 16px;
            page-break-inside: avoid;
        }

        .totals-table {
            width: 45%;
            margin-left: 55%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 5px 8px;
            font-size: 9px;
        }

        .totals-table td.label {
            color: #6b7280;
            text-align: right;
        }

        .totals-table td.value {
            color: #111827;
            text-align: right;
            font-weight: bold;
            width: 110px;
        }

        .totals-table tr.grand-total td {
            border-top: 2px solid #4F46E5;
            padding-top: 8px;
            font-size: 11px;
            color: #4F46E5;
        }

        .totals-table tr.grand-total td.value {
            color: #4F46E5;
            font-size: 12px;
        }

        /* ============================================================
           NOTICES
           ============================================================ */
        .notice {
            margin-top: 10px;
            padding: 8px 10px;
            border-radius: 4px;
            font-size: 8.5px;
        }

        .notice-danger  { background: #fee2e2; color: #991b1b; border-left: 3px solid #dc2626; }
        .notice-info    { background: #dbeafe; color: #1e40af; border-left: 3px solid #3b82f6; }
        .notice-success { background: #d1fae5; color: #065f46; border-left: 3px solid #10b981; }

        /* ============================================================
           SIGNATURE / CERTIFICATION
           ============================================================ */
        .signature-block {
            margin-top: 30px;
            page-break-inside: avoid;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }

        .signature-table td {
            width: 50%;
            padding: 30px 20px 0 0;
            vertical-align: bottom;
            font-size: 8.5px;
            color: #6b7280;
        }

        .signature-line {
            border-top: 1px solid #9ca3af;
            padding-top: 4px;
            margin-top: 30px;
        }

        .signature-label {
            font-weight: bold;
            color: #374151;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>

{{-- ================================================================
     FIXED HEADER — repeated on every page
================================================================ --}}
<div class="pdf-header">
    <table>
        <tr>
            <td class="logo-cell">
                @if(!empty($settings->system_logo) && file_exists(public_path($settings->system_logo)))
                    <img src="{{ public_path($settings->system_logo) }}" alt="Logo">
                @else
                    <div class="logo-fallback">
                        {{ strtoupper(substr($settings->system_name ?? $settings->company_name ?? 'H', 0, 1)) }}
                    </div>
                @endif
            </td>
            <td class="title-cell">
                <div class="company-name">
                    {{ $settings->system_name ?? $settings->company_name ?? 'Property Management System' }}
                </div>
                <div class="company-meta">
                    @if(!empty($settings->system_email)) {{ $settings->system_email }} @endif
                    @if(!empty($settings->system_phone)) • {{ $settings->system_phone }} @endif
                    @if(!empty($settings->system_address)) • {{ $settings->system_address }} @endif
                </div>
            </td>
            <td class="report-cell">
                <div class="report-label">Report</div>
                <div class="report-title">Invoice Management Report</div>
                <div class="report-generated">Generated {{ now()->format('M d, Y • H:i') }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- ================================================================
     FIXED FOOTER — repeated on every page
================================================================ --}}
<div class="pdf-footer">
    <table>
        <tr>
            <td style="text-align: left;">
                {{ $settings->system_name ?? $settings->company_name ?? 'Property Management System' }}
                — Confidential
            </td>
            <td style="text-align: center;">
                This is a computer-generated document. No signature is required.
            </td>
            <td style="text-align: right;">
                Page <span class="page-number"></span>
            </td>
        </tr>
    </table>
</div>

{{-- ================================================================
     KPI STRIP
================================================================ --}}
<div class="report-section">
    <table class="kpi-strip">
        <tr>
            <td class="kpi-info">
                <div class="kpi-label">Total Invoices</div>
                <div class="kpi-value">{{ number_format($statistics['total_invoices'] ?? 0) }}</div>
                <div class="kpi-sub">Excludes consolidated</div>
            </td>
            <td class="kpi-success">
                <div class="kpi-label">Paid</div>
                <div class="kpi-value">{{ number_format($statistics['paid_invoices'] ?? 0) }}</div>
                <div class="kpi-sub">{{ number_format($statistics['collection_rate'] ?? 0, 1) }}% collection rate</div>
            </td>
            <td class="kpi-warning">
                <div class="kpi-label">Pending</div>
                <div class="kpi-value">{{ number_format($statistics['pending_invoices'] ?? 0) }}</div>
                <div class="kpi-sub">Awaiting payment</div>
            </td>
            <td class="kpi-danger">
                <div class="kpi-label">Overdue</div>
                <div class="kpi-value">{{ number_format($statistics['overdue_invoices'] ?? 0) }}</div>
                <div class="kpi-sub">Past due date</div>
            </td>
            <td class="kpi-success">
                <div class="kpi-label">Total Revenue</div>
                <div class="kpi-value">{{ $settings->formatAmount($statistics['total_revenue'] ?? 0) }}</div>
                <div class="kpi-sub">Collected</div>
            </td>
            <td class="kpi-warning">
                <div class="kpi-label">Total Due</div>
                <div class="kpi-value">{{ $settings->formatAmount($statistics['total_due'] ?? 0) }}</div>
                <div class="kpi-sub">Outstanding</div>
            </td>
        </tr>
    </table>
</div>

{{-- ================================================================
     APPLIED FILTERS
================================================================ --}}
@php
    $hasFilters = !empty($filters) && (
        !empty($filters['property_id']) ||
        !empty($filters['status']) ||
        !empty($filters['type']) ||
        !empty($filters['period']) ||
        !empty($filters['search']) ||
        !empty($filters['has_coverage']) ||
        !empty($filters['is_bulk'])
    );
@endphp

@if($hasFilters)
<div class="filters-block">
    <div class="filters-title">Applied Filters</div>
    <table>
        <tr>
            @if(!empty($filters['property_id']))
                @php $selectedProperty = \App\Models\Property::find($filters['property_id']); @endphp
                @if($selectedProperty)
                    <td>
                        <span class="filter-chip">
                            <span class="label">Property</span>
                            {{ $selectedProperty->house_number }} {{ $selectedProperty->street_name }}
                        </span>
                    </td>
                @endif
            @endif
            @if(!empty($filters['status']))
                <td>
                    <span class="filter-chip">
                        <span class="label">Status</span>
                        {{ ucfirst($filters['status']) }}
                    </span>
                </td>
            @endif
            @if(!empty($filters['type']))
                <td>
                    <span class="filter-chip">
                        <span class="label">Type</span>
                        {{ ucfirst($filters['type']) }}
                    </span>
                </td>
            @endif
            @if(!empty($filters['period']))
                <td>
                    <span class="filter-chip">
                        <span class="label">Period</span>
                        {{ \Carbon\Carbon::parse($filters['period'] . '-01')->format('F Y') }}
                    </span>
                </td>
            @endif
            @if(!empty($filters['search']))
                <td>
                    <span class="filter-chip">
                        <span class="label">Search</span>
                        "{{ $filters['search'] }}"
                    </span>
                </td>
            @endif
            @if(!empty($filters['has_coverage']))
                <td>
                    <span class="filter-chip">
                        <span class="label">Coverage</span>
                        {{ ucfirst($filters['has_coverage']) }}
                    </span>
                </td>
            @endif
            @if(!empty($filters['is_bulk']))
                <td>
                    <span class="filter-chip">
                        <span class="label">Bulk</span>
                        {{ ucfirst($filters['is_bulk']) }}
                    </span>
                </td>
            @endif
        </tr>
    </table>
</div>
@endif

{{-- ================================================================
     INVOICES TABLE
================================================================ --}}
<div class="report-section">
    <div class="section-title">Invoice Detail — {{ number_format($invoices->count()) }} record(s)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 24px;" class="text-center">#</th>
                <th style="width: 74px;">Invoice #</th>
                <th>Property</th>
                <th>Landlord</th>
                <th style="width: 90px;">Period</th>
                <th style="width: 60px;">Due Date</th>
                <th style="width: 68px;" class="text-right">Amount</th>
                <th style="width: 55px;" class="text-right">Penalty</th>
                <th style="width: 68px;" class="text-right">Total</th>
                <th style="width: 60px;">Status</th>
                <th style="width: 46px;">Type</th>
                <th style="width: 64px;">Method</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoices as $index => $invoice)
                @php
                    $statusBadge = match($invoice->status) {
                        'paid'         => 'badge-paid',
                        'pending'      => 'badge-pending',
                        'overdue'      => 'badge-overdue',
                        'processing'   => 'badge-processing',
                        'partial'      => 'badge-partial',
                        'cancelled'    => 'badge-cancelled',
                        'consolidated' => 'badge-consolidated',
                        default        => 'badge-cancelled',
                    };

                    $periodDisplay = '—';
                    try {
                        if ($invoice->is_bulk_payment && $invoice->bulk_coverage_start && $invoice->bulk_coverage_end) {
                            $periodDisplay = \Carbon\Carbon::parse($invoice->bulk_coverage_start . '-01')->format('M Y')
                                . ' – '
                                . \Carbon\Carbon::parse($invoice->bulk_coverage_end . '-01')->format('M Y');
                        } elseif ($invoice->period && preg_match('/^\d{4}-\d{2}$/', $invoice->period)) {
                            $periodDisplay = \Carbon\Carbon::parse($invoice->period . '-01')->format('M Y');
                        } else {
                            $periodDisplay = $invoice->period ?: '—';
                        }
                    } catch (\Throwable $e) {
                        $periodDisplay = $invoice->period ?: '—';
                    }
                @endphp
                <tr>
                    <td class="text-center muted">{{ $index + 1 }}</td>

                    <td class="mono">
                        <strong>INV-{{ str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</strong>
                        @if($invoice->payment_reference)
                            <span class="sub">Ref: {{ $invoice->payment_reference }}</span>
                        @endif
                    </td>

                    <td>
                        {{ $invoice->property?->house_number }} {{ $invoice->property?->street_name }}
                        @if($invoice->property?->block_number)
                            <span class="sub">Block {{ $invoice->property->block_number }}</span>
                        @endif
                    </td>

                    <td>{{ $invoice->property?->landlord?->name ?? '—' }}</td>

                    <td>{{ $periodDisplay }}</td>

                    <td>{{ $invoice->due_date?->format('M d, Y') ?? '—' }}</td>

                    <td class="text-right amount">{{ $settings->formatAmount($invoice->amount) }}</td>

                    <td class="text-right">
                        @if($invoice->penalty_amount > 0)
                            <span class="penalty">+{{ $settings->formatAmount($invoice->penalty_amount) }}</span>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>

                    <td class="text-right amount">{{ $settings->formatAmount($invoice->total_amount) }}</td>

                    <td>
                        <span class="badge {{ $statusBadge }}">
                            {{ $invoice->status === 'consolidated' ? 'In Bulk' : ucfirst($invoice->status) }}
                        </span>
                    </td>

                    <td>
                        @if($invoice->is_bulk_payment)
                            <span class="tag tag-bulk">Bulk</span>
                        @elseif($invoice->bulk_payment_id)
                            <span class="tag tag-child">Child</span>
                        @else
                            <span class="tag tag-regular">Regular</span>
                        @endif
                    </td>

                    <td>
                        @if($invoice->payment_method)
                            {{ ucfirst(str_replace('_', ' ', $invoice->payment_method)) }}
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="text-center" style="padding: 40px; color: #9ca3af;">
                        No invoices match the selected criteria.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- ================================================================
     TOTALS BLOCK
================================================================ --}}
@php
    $totalAmount    = $invoices->sum('total_amount');
    $totalPenalties = $invoices->sum('penalty_amount');
    $totalPaid      = $invoices->where('status', 'paid')->sum('total_amount');
    $totalOverdue   = $invoices->where('status', 'overdue')->sum('total_amount');
    $overdueCount   = $invoices->where('status', 'overdue')->count();
    $paidBulkCount  = $invoices->where('is_bulk_payment', true)->where('status', 'paid')->count();
@endphp

<div class="totals-block">
    <table class="totals-table">
        <tr>
            <td class="label">Record Count</td>
            <td class="value">{{ number_format($invoices->count()) }}</td>
        </tr>
        <tr>
            <td class="label">Total Penalties</td>
            <td class="value">{{ $settings->formatAmount($totalPenalties) }}</td>
        </tr>
        <tr>
            <td class="label">Paid</td>
            <td class="value">{{ $settings->formatAmount($totalPaid) }}</td>
        </tr>
        @if($totalOverdue > 0)
        <tr>
            <td class="label">Overdue</td>
            <td class="value" style="color: #dc2626;">{{ $settings->formatAmount($totalOverdue) }}</td>
        </tr>
        @endif
        <tr class="grand-total">
            <td class="label">Total Amount</td>
            <td class="value">{{ $settings->formatAmount($totalAmount) }}</td>
        </tr>
    </table>
</div>

{{-- ================================================================
     NOTICES
================================================================ --}}
@if($overdueCount > 0)
<div class="notice notice-danger">
    <strong>⚠ Overdue Alert —</strong>
    {{ $overdueCount }} invoice(s) totaling {{ $settings->formatAmount($totalOverdue) }} are past due.
    Follow-up action is recommended.
</div>
@endif

@if($paidBulkCount > 0)
<div class="notice notice-info" style="margin-top: 8px;">
    <strong>📦 Active Bulk Coverage —</strong>
    {{ $paidBulkCount }} bulk invoice(s) with active coverage. Future monthly invoices will be
    suppressed for the periods those bulk payments cover.
</div>
@endif

@if($overdueCount === 0 && $totalPenalties == 0)
<div class="notice notice-success" style="margin-top: 8px;">
    <strong>✓ Healthy Portfolio —</strong>
    No overdue invoices and no outstanding penalties at the time of this report.
</div>
@endif

{{-- ================================================================
     SIGNATURE / CERTIFICATION
================================================================ --}}
<div class="signature-block">
    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-line"></div>
                <div class="signature-label">Prepared By</div>
                <div>{{ auth()->user()?->name ?? 'System' }}</div>
                <div>{{ now()->format('M d, Y H:i') }}</div>
            </td>
            <td>
                <div class="signature-line"></div>
                <div class="signature-label">Reviewed / Approved By</div>
                <div>&nbsp;</div>
                <div>&nbsp;</div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>