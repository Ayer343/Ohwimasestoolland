{{-- resources/views/admin/invoices/pdf-single.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice INV-{{ str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</title>
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
           FIXED HEADER / FOOTER
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

        .pdf-header .doc-cell {
            vertical-align: middle;
            text-align: right;
        }

        .pdf-header .doc-label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #6b7280;
        }

        .pdf-header .doc-title {
            font-size: 12px;
            font-weight: bold;
            color: #4F46E5;
        }

        .pdf-header .doc-number {
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
           LAYOUT PRIMITIVES
           ============================================================ */
        .section {
            margin-bottom: 16px;
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

        /* ============================================================
           DOCUMENT TITLE STRIP
           ============================================================ */
        .doc-strip {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .doc-strip td {
            vertical-align: top;
            padding: 12px 14px;
            background: #eef2ff;
            border-left: 4px solid #4F46E5;
            border-radius: 4px;
        }

        .doc-strip .label {
            font-size: 8px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 3px;
        }

        .doc-strip .value {
            font-size: 14px;
            font-weight: bold;
            color: #111827;
        }

        .doc-strip .muted {
            color: #9ca3af;
            font-weight: normal;
            font-size: 9px;
        }

        /* ============================================================
           PARTIES (SENDER / RECIPIENT)
           ============================================================ */
        .parties {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
            margin-bottom: 16px;
        }

        .parties td {
            width: 50%;
            vertical-align: top;
            padding: 12px 14px;
            background: #f9fafb;
            border-radius: 4px;
        }

        .parties .party-label {
            font-size: 8px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 6px;
        }

        .parties .party-name {
            font-size: 11px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 4px;
        }

        .parties .party-line {
            font-size: 9px;
            color: #4b5563;
            line-height: 1.5;
        }

        .parties .party-line .k {
            color: #9ca3af;
            margin-right: 4px;
        }

        /* ============================================================
           META GRID (period, dates, status)
           ============================================================ */
        .meta-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .meta-grid td {
            width: 25%;
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .meta-grid td:first-child { border-left-width: 2px; border-left-color: #4F46E5; }

        .meta-label {
            font-size: 7.5px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .meta-value {
            font-size: 11px;
            font-weight: bold;
            color: #111827;
        }

        .meta-value.muted {
            color: #6b7280;
            font-weight: normal;
            font-size: 10px;
        }

        /* ============================================================
           ITEMS TABLE
           ============================================================ */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .items-table thead {
            display: table-header-group;
        }

        .items-table th {
            background: #4F46E5;
            color: #fff;
            text-align: left;
            padding: 8px 10px;
            font-weight: bold;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .items-table th.text-right { text-align: right; }

        .items-table td {
            padding: 9px 10px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 10px;
            color: #374151;
            vertical-align: top;
        }

        .items-table td.text-right {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .items-table tr.item-row td { background: #ffffff; }
        .items-table tr.item-row:nth-child(even) td { background: #fafbfc; }

        .items-table tr.summary-row td {
            border-bottom: 1px solid #e5e7eb;
            font-size: 10px;
        }

        .items-table tr.summary-row.discount td { color: #059669; }
        .items-table tr.summary-row.penalty td { color: #dc2626; }
        .items-table tr.summary-row.paid td { color: #059669; }

        .items-table tr.grand-total td {
            background: #eef2ff;
            border-top: 2px solid #4F46E5;
            border-bottom: none;
            font-size: 12px;
            font-weight: bold;
            color: #4F46E5;
            padding-top: 12px;
            padding-bottom: 12px;
        }

        .items-table tr.grand-total td.text-right {
            font-size: 14px;
        }

        .items-table .item-desc {
            color: #6b7280;
            font-size: 8.5px;
            margin-top: 2px;
        }

        /* ============================================================
           AMOUNT IN WORDS
           ============================================================ */
        .amount-in-words {
            background: #f9fafb;
            border-left: 3px solid #4F46E5;
            padding: 8px 12px;
            margin-bottom: 16px;
            font-size: 9px;
            color: #4b5563;
        }

        .amount-in-words .label {
            font-size: 7.5px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }

        .amount-in-words .value {
            font-size: 10px;
            font-weight: bold;
            color: #111827;
            text-transform: capitalize;
        }

        /* ============================================================
           NOTICES
           ============================================================ */
        .notice {
            padding: 10px 12px;
            border-radius: 4px;
            font-size: 9px;
            margin-bottom: 12px;
        }

        .notice-title {
            font-weight: bold;
            margin-bottom: 4px;
            font-size: 9.5px;
        }

        .notice-success { background: #d1fae5; color: #065f46; border-left: 3px solid #10b981; }
        .notice-warning { background: #fef3c7; color: #92400e; border-left: 3px solid #f59e0b; }
        .notice-danger  { background: #fee2e2; color: #991b1b; border-left: 3px solid #dc2626; }
        .notice-info    { background: #dbeafe; color: #1e40af; border-left: 3px solid #3b82f6; }

        /* ============================================================
           COVERAGE
           ============================================================ */
        .coverage-strip {
            padding: 10px 12px;
            background: #eef2ff;
            border-radius: 4px;
            margin-bottom: 16px;
        }

        .coverage-strip .coverage-title {
            font-weight: bold;
            color: #3730a3;
            font-size: 9.5px;
            margin-bottom: 6px;
        }

        .coverage-strip .period-chip {
            display: inline-block;
            background: #ffffff;
            border: 1px solid #c7d2fe;
            color: #3730a3;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
            margin: 2px 3px 2px 0;
        }

        /* ============================================================
           CHILD INVOICES TABLE
           ============================================================ */
        .children-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            margin-bottom: 16px;
        }

        .children-table th {
            background: #f3f4f6;
            color: #374151;
            text-align: left;
            padding: 6px 8px;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-bottom: 1px solid #e5e7eb;
        }

        .children-table th.text-right { text-align: right; }

        .children-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #f3f4f6;
            color: #4b5563;
        }

        .children-table td.text-right {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        /* ============================================================
           CERTIFICATION / SIGNATURE
           ============================================================ */
        .signature-block {
            margin-top: 24px;
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

        /* Utilities */
        .muted { color: #9ca3af; }
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
            <td class="doc-cell">
                <div class="doc-label">Invoice</div>
                <div class="doc-title">INV-{{ str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</div>
                <div class="doc-number">{{ $invoice->created_at?->format('M d, Y') }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- ================================================================
     FIXED FOOTER
================================================================ --}}
<div class="pdf-footer">
    <table>
        <tr>
            <td style="text-align: left;">
                {{ $settings->system_name ?? $settings->company_name ?? 'Property Management System' }}
                — Confidential
            </td>
            <td style="text-align: center;">
                INV-{{ str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }} • Computer-generated document
            </td>
            <td style="text-align: right;">
                Page <span class="page-number"></span>
            </td>
        </tr>
    </table>
</div>

@php
    // ── Precompute everything once ──
    $invoiceNumber = 'INV-' . str_pad($invoice->id, 6, '0', STR_PAD_LEFT);

    $statusToken = match ($invoice->status) {
        'paid'         => 'success',
        'pending'      => 'warning',
        'overdue'      => 'danger',
        'processing'   => 'info',
        'partial'      => 'info',
        'cancelled'    => 'secondary',
        'consolidated' => 'info',
        default        => 'secondary',
    };

    $statusLabel = $invoice->status === 'consolidated'
        ? 'In Bulk Payment'
        : ucfirst($invoice->status);

    // Period display
    $periodDisplay = '—';
    try {
        if ($invoice->is_bulk_payment && $invoice->bulk_coverage_start && $invoice->bulk_coverage_end) {
            $periodDisplay = \Carbon\Carbon::parse($invoice->bulk_coverage_start . '-01')->format('F Y')
                . ' – ' .
                \Carbon\Carbon::parse($invoice->bulk_coverage_end . '-01')->format('F Y');
        } elseif ($invoice->period && preg_match('/^\d{4}-\d{2}$/', $invoice->period)) {
            $periodDisplay = \Carbon\Carbon::parse($invoice->period . '-01')->format('F Y');
        } elseif ($invoice->period) {
            $periodDisplay = $invoice->period;
        }
    } catch (\Throwable $e) {
        $periodDisplay = $invoice->period ?: '—';
    }

    // Amounts
    $baseAmount    = (float) ($invoice->amount ?? 0);
    $penaltyAmount = (float) ($invoice->penalty_amount ?? 0);
    $discount      = (float) ($invoice->discount_amount ?? 0);
    $paidAmount    = (float) ($invoice->paid_amount ?? 0);
    $totalAmount   = (float) ($invoice->total_amount ?? ($baseAmount + $penaltyAmount - $discount));
    $balance       = (float) ($invoice->balance ?? ($totalAmount - $paidAmount));

    // Amount in words — small converter for readability
    $amountForWords = $invoice->status === 'paid' ? $totalAmount : $balance;
    if ($amountForWords <= 0) {
        $amountForWords = $totalAmount;
    }
    $amountInWords = function ($n) {
        $n = (int) round($n);
        if ($n === 0) return 'zero';
        $units = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
                  'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
                  'seventeen', 'eighteen', 'nineteen'];
        $tens  = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
        $scales = ['', ' thousand', ' million', ' billion'];

        $words = '';
        $i = 0;
        while ($n > 0) {
            $chunk = $n % 1000;
            if ($chunk > 0) {
                $h = intdiv($chunk, 100);
                $r = $chunk % 100;
                $part = '';
                if ($h) { $part .= $units[$h] . ' hundred'; }
                if ($r) {
                    if ($part) { $part .= ' and '; }
                    if ($r < 20) {
                        $part .= $units[$r];
                    } else {
                        $part .= $tens[intdiv($r, 10)];
                        if ($r % 10) { $part .= '-' . $units[$r % 10]; }
                    }
                }
                $words = $part . $scales[$i] . ($words ? ' ' . $words : '');
            }
            $n = intdiv($n, 1000);
            $i++;
        }
        return trim($words);
    };

    $currencyLabel = $settings->currency_code ?? 'GHS';
    $amountInWordsText = $amountInWords($amountForWords) . ' ' . $currencyLabel . ' only';

    // Coverage periods
    $coveragePeriods = $invoice->covers_periods;
    if (is_string($coveragePeriods)) {
        $decoded = json_decode($coveragePeriods, true);
        $coveragePeriods = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($coveragePeriods)) {
        $coveragePeriods = [];
    }

    // Bulk coverage active?
    $isCoverageActive = $invoice->is_bulk_payment && $invoice->isPaid() && !empty($coveragePeriods);
@endphp

{{-- ================================================================
     DOCUMENT TITLE STRIP
================================================================ --}}
<table class="doc-strip">
    <tr>
        <td style="width: 40%;">
            <div class="label">Document</div>
            <div class="value">Invoice</div>
        </td>
        <td style="width: 30%;">
            <div class="label">Invoice Number</div>
            <div class="value" style="font-family: 'DejaVu Sans Mono', monospace;">{{ $invoiceNumber }}</div>
        </td>
        <td style="width: 30%;">
            <div class="label">Amount {{ $invoice->status === 'paid' ? 'Paid' : 'Due' }}</div>
            <div class="value">
                {{ $settings->formatAmount($invoice->status === 'paid' ? $totalAmount : $balance) }}
            </div>
        </td>
    </tr>
</table>

{{-- ================================================================
     SENDER / RECIPIENT
================================================================ --}}
<table class="parties">
    <tr>
        <td>
            <div class="party-label">From</div>
            <div class="party-name">
                {{ $settings->system_name ?? $settings->company_name ?? 'Property Management System' }}
            </div>
            <div class="party-line">
                @if(!empty($settings->system_address))
                    <div>{{ $settings->system_address }}</div>
                @endif
                @if(!empty($settings->system_email))
                    <div><span class="k">Email</span> {{ $settings->system_email }}</div>
                @endif
                @if(!empty($settings->system_phone))
                    <div><span class="k">Phone</span> {{ $settings->system_phone }}</div>
                @endif
                @if(!empty($settings->tax_id))
                    <div><span class="k">Tax ID</span> {{ $settings->tax_id }}</div>
                @endif
            </div>
        </td>
        <td>
            <div class="party-label">Bill To</div>
            <div class="party-name">{{ $invoice->property?->landlord?->name ?? 'Landlord' }}</div>
            <div class="party-line">
                @if($invoice->property)
                    <div>
                        {{ $invoice->property->house_number }} {{ $invoice->property->street_name }}
                        @if($invoice->property->block_number)
                            , Block {{ $invoice->property->block_number }}
                        @endif
                    </div>
                    @if($invoice->property->zone)
                        <div>Zone: {{ $invoice->property->zone }}</div>
                    @endif
                @endif
                @if($invoice->property?->landlord?->email)
                    <div><span class="k">Email</span> {{ $invoice->property->landlord->email }}</div>
                @endif
                @if($invoice->property?->landlord?->phone)
                    <div><span class="k">Phone</span> {{ $invoice->property->landlord->phone }}</div>
                @endif
            </div>
        </td>
    </tr>
</table>

{{-- ================================================================
     META GRID
================================================================ --}}
<table class="meta-grid">
    <tr>
        <td>
            <div class="meta-label">Issue Date</div>
            <div class="meta-value">{{ $invoice->created_at?->format('M d, Y') ?? '—' }}</div>
        </td>
        <td>
            <div class="meta-label">Due Date</div>
            <div class="meta-value">{{ $invoice->due_date?->format('M d, Y') ?? '—' }}</div>
            @if($invoice->status === 'overdue' && $invoice->due_date)
                <div style="font-size: 8px; color: #dc2626; margin-top: 2px;">
                    {{ $invoice->due_date->diffInDays(now()) }} day(s) overdue
                </div>
            @endif
        </td>
        <td>
            <div class="meta-label">Billing Period</div>
            <div class="meta-value" style="font-size: 10px;">{{ $periodDisplay }}</div>
        </td>
        <td>
            <div class="meta-label">Status</div>
            <div class="meta-value" style="color: var(--{{ $statusToken }});">
                {{ $statusLabel }}
            </div>
        </td>
    </tr>
</table>

{{-- ================================================================
     ITEMS TABLE
================================================================ --}}
<table class="items-table">
    <thead>
        <tr>
            <th style="width: 60%;">Description</th>
            <th style="width: 40%;" class="text-right">Amount</th>
        </tr>
    </thead>
    <tbody>
        <tr class="item-row">
            <td>
                <strong>
                    @if($invoice->is_bulk_payment)
                        Bulk Payment — {{ $invoice->bulk_months ?? '—' }} months
                    @else
                        Monthly Dues
                    @endif
                </strong>
                <div class="item-desc">
                    @if($invoice->is_bulk_payment)
                        Covers {{ $periodDisplay }}
                        @if(!empty($invoice->description))
                            <br>{{ $invoice->description }}
                        @endif
                    @else
                        {{ $periodDisplay }}
                        @if(!empty($invoice->description))
                            <br>{{ $invoice->description }}
                        @endif
                    @endif
                </div>
            </td>
            <td class="text-right">{{ $settings->formatAmount($baseAmount) }}</td>
        </tr>

        @if($discount > 0)
        <tr class="summary-row discount">
            <td>Discount{{ $invoice->discount_percentage ? ' (' . $invoice->discount_percentage . '%)' : '' }}</td>
            <td class="text-right">−{{ $settings->formatAmount($discount) }}</td>
        </tr>
        @endif

        @if($penaltyAmount > 0)
        <tr class="summary-row penalty">
            <td>Late Payment Penalty</td>
            <td class="text-right">+{{ $settings->formatAmount($penaltyAmount) }}</td>
        </tr>
        @endif

        <tr class="summary-row">
            <td style="font-weight: bold;">Total</td>
            <td class="text-right" style="font-weight: bold;">{{ $settings->formatAmount($totalAmount) }}</td>
        </tr>

        @if($paidAmount > 0 && $invoice->status !== 'paid')
        <tr class="summary-row paid">
            <td>Amount Paid</td>
            <td class="text-right">−{{ $settings->formatAmount($paidAmount) }}</td>
        </tr>
        @endif

        <tr class="grand-total">
            <td>
                {{ $invoice->status === 'paid' ? 'Amount Paid' : 'Balance Due' }}
            </td>
            <td class="text-right">
                {{ $settings->formatAmount($invoice->status === 'paid' ? $totalAmount : $balance) }}
            </td>
        </tr>
    </tbody>
</table>

{{-- ================================================================
     AMOUNT IN WORDS
================================================================ --}}
<div class="amount-in-words">
    <div class="label">Amount in Words</div>
    <div class="value">{{ $amountInWordsText }}</div>
</div>

{{-- ================================================================
     STATUS NOTICES
================================================================ --}}
@if($invoice->status === 'paid')
    <div class="notice notice-success">
        <div class="notice-title">✓ Paid in Full</div>
        Payment received on
        {{ $invoice->payment_date?->format('M d, Y') ?? '—' }}
        @if($invoice->payment_method)
            via {{ ucfirst(str_replace('_', ' ', $invoice->payment_method)) }}
        @endif
        @if($invoice->payment_reference)
            • Reference <strong>{{ $invoice->payment_reference }}</strong>
        @endif
        .
    </div>
@elseif($invoice->status === 'overdue')
    <div class="notice notice-danger">
        <div class="notice-title">⚠ Payment Overdue</div>
        This invoice is past its due date of {{ $invoice->due_date?->format('M d, Y') }}.
        Please remit payment immediately to avoid further penalties.
    </div>
@elseif($invoice->status === 'consolidated')
    <div class="notice notice-info">
        <div class="notice-title">📦 Consolidated Invoice</div>
        This invoice has been folded into a bulk payment
        @if($invoice->bulk_payment_id)
            (<strong>{{ $invoiceNumber }}</strong> →
            INV-{{ str_pad($invoice->bulk_payment_id, 6, '0', STR_PAD_LEFT) }})
        @endif
        and should not be paid individually.
    </div>
@elseif($invoice->status === 'pending')
    <div class="notice notice-warning">
        <div class="notice-title">⏳ Payment Pending</div>
        Please settle this invoice by {{ $invoice->due_date?->format('M d, Y') }}.
    </div>
@endif

{{-- ================================================================
     BULK COVERAGE
================================================================ --}}
@if($isCoverageActive && !empty($coveragePeriods))
    <div class="coverage-strip">
        <div class="coverage-title">
            📦 Active Bulk Coverage — {{ count($coveragePeriods) }} period(s)
        </div>
        <div style="font-size: 9px; color: #3730a3; margin-bottom: 6px;">
            No further invoices will be generated for the periods listed below.
        </div>
        @foreach($coveragePeriods as $period)
            <span class="period-chip">
                {{ \Carbon\Carbon::parse($period . '-01')->format('M Y') }}
            </span>
        @endforeach
    </div>
@endif

{{-- ================================================================
     CHILD INVOICES (for bulk payments)
================================================================ --}}
@if($invoice->is_bulk_payment && $invoice->childInvoices && $invoice->childInvoices->count() > 0)
    <div class="section">
        <div class="section-title">Consolidated Invoices ({{ $invoice->childInvoices->count() }})</div>
        <table class="children-table">
            <thead>
                <tr>
                    <th style="width: 30%;">Invoice #</th>
                    <th style="width: 30%;">Period</th>
                    <th style="width: 20%;" class="text-right">Amount</th>
                    <th style="width: 20%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->childInvoices as $child)
                    <tr>
                        <td style="font-family: 'DejaVu Sans Mono', monospace;">
                            INV-{{ str_pad($child->id, 6, '0', STR_PAD_LEFT) }}
                        </td>
                        <td>
                            @if($child->period && preg_match('/^\d{4}-\d{2}$/', $child->period))
                                {{ \Carbon\Carbon::parse($child->period . '-01')->format('M Y') }}
                            @else
                                {{ $child->period ?: '—' }}
                            @endif
                        </td>
                        <td class="text-right">{{ $settings->formatAmount($child->amount) }}</td>
                        <td><span class="muted">Consolidated</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- ================================================================
     PAYMENT INSTRUCTIONS
================================================================ --}}
@if(in_array($invoice->status, ['pending', 'overdue']))
    <div class="notice notice-warning" style="padding: 12px 14px;">
        <div class="notice-title">💰 Payment Instructions</div>

        <div style="font-size: 9px; line-height: 1.6; margin-top: 6px;">

            @if(!empty($settings->payment_mobile_number))
                <strong>Mobile Money</strong><br>
                Network: {{ $settings->payment_network ?? 'All Networks' }}<br>
                Number: {{ $settings->payment_mobile_number }}<br>
                Account Name: {{ $settings->payment_account_name ?? '—' }}<br>
            @endif

            @if(!empty($settings->bank_account_number))
                <div style="margin-top: 6px;">
                    <strong>Bank Transfer</strong><br>
                    Bank: {{ $settings->bank_name ?? '—' }}<br>
                    Account Number: {{ $settings->bank_account_number }}<br>
                    Account Name: {{ $settings->bank_account_name ?? '—' }}<br>
                </div>
            @endif

            <div style="margin-top: 8px; padding-top: 6px; border-top: 1px solid #fbbf24;">
                <strong>Payment Reference:</strong>
                Please quote <strong>{{ $invoiceNumber }}</strong> on all payments.
            </div>

        </div>
    </div>
@endif

{{-- ================================================================
     CERTIFICATION / SIGNATURE
================================================================ --}}
<div class="signature-block">
    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-line"></div>
                <div class="signature-label">Issued By</div>
                <div>{{ $invoice->creator?->name ?? ($settings->system_name ?? 'System') }}</div>
                <div>{{ $invoice->created_at?->format('M d, Y H:i') ?? now()->format('M d, Y H:i') }}</div>
            </td>
            <td>
                <div class="signature-line"></div>
                <div class="signature-label">Received By</div>
                <div>&nbsp;</div>
                <div>&nbsp;</div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>