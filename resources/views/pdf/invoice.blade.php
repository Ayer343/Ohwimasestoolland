{{-- resources/views/pdf/invoice.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->reference }}</title>
    <style>
        /* ============================================================
           PDF — Optimized for DomPDF
           (plain hex colors, no flexbox, no webfonts, no CSS vars)
           ============================================================ */
        @page {
            margin: 40px 45px 60px 45px;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', 'Helvetica', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #2c3e50;
            margin: 0;
            padding: 0;
        }

        /* ============ HEADER ============ */
        .doc-header {
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }

        .doc-header .brand {
            font-size: 9px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .doc-header h1 {
            font-size: 24px;
            letter-spacing: 2px;
            margin: 6px 0 4px;
            color: #1a2332;
        }

        .doc-header h2 {
            font-size: 13px;
            font-weight: normal;
            color: #54637a;
            margin: 0 0 8px;
        }

        .doc-meta {
            font-size: 10px;
            color: #7f8c8d;
        }

        .doc-meta .meta-item {
            display: inline-block;
            margin-right: 16px;
        }

        /* ============ STATUS BADGE ============ */
        .status-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-partial { background: #cce5ff; color: #004085; }
        .status-paid    { background: #d4edda; color: #155724; }
        .status-overdue { background: #f8d7da; color: #721c24; }
        .status-void    { background: #e2e3e5; color: #383d41; }

        /* ============ AMOUNT BOX ============ */
        .amount-box {
            border: 2px solid #2c3e50;
            background: #f6f8fa;
            padding: 16px;
            text-align: center;
            margin-bottom: 20px;
        }

        .amount-box .label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #7f8c8d;
            margin-bottom: 4px;
        }

        .amount-box .value {
            font-size: 28px;
            font-weight: bold;
            color: #1a2332;
        }

        .amount-box .sub-line {
            font-size: 10px;
            color: #7f8c8d;
            margin-top: 6px;
        }

        /* ============ SUMMARY TABLE ============ */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .summary-table td {
            padding: 8px 12px;
            border-bottom: 1px dashed #cfd6df;
            font-size: 11px;
        }

        .summary-table td:first-child { color: #54637a; }

        .summary-table td:last-child {
            text-align: right;
            font-weight: bold;
            color: #1a2332;
        }

        .summary-table tr.total td {
            border-top: 2px solid #2c3e50;
            border-bottom: none;
            padding-top: 10px;
            font-size: 12px;
        }

        /* ============ INFO TABLE ============ */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .info-table th,
        .info-table td {
            border: 1px solid #dfe4ea;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
            font-size: 10.5px;
        }

        .info-table th {
            background: #f6f8fa;
            width: 32%;
            font-weight: bold;
            color: #2c3e50;
        }

        /* ============ SECTION ============ */
        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #2c3e50;
            border-bottom: 1px solid #cfd6df;
            padding-bottom: 4px;
            margin-bottom: 12px;
        }

        /* ============ NOTES ============ */
        .notes-box {
            border-left: 4px solid #3498db;
            background: #f6f8fa;
            padding: 10px 12px;
            font-size: 10.5px;
            white-space: pre-line;
        }

        .void-box {
            border-left: 4px solid #e74c3c;
            background: #fdecea;
            padding: 10px 12px;
            font-size: 10.5px;
            color: #c0392b;
        }

        /* ============ FOOTER ============ */
        .doc-footer {
            position: fixed;
            bottom: -35px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8.5px;
            color: #95a5a6;
            border-top: 1px solid #dfe4ea;
            padding-top: 6px;
        }

        .doc-footer .page-number:after { content: counter(page); }

        /* ============ UTILITIES ============ */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-muted { color: #7f8c8d; }
        .text-bold { font-weight: bold; }
        .text-success { color: #27ae60; }
        .text-danger { color: #e74c3c; }
        .text-warning { color: #f39c12; }
        .mt-10 { margin-top: 10px; }
        .mb-0 { margin-bottom: 0; }
        .clearfix { clear: both; }
    </style>
</head>
<body>
    @php
        // ========== CONFIG FALLBACKS ==========
        $currencySymbol = $currency_symbol ?? config('leases.ghana.currency.symbol', 'GH₵');
        $governingLaw   = $governing_law   ?? config('leases.ghana.governing_law', 'Rent Act, 1963 (Act 220)');

        // ========== NUMERIC SNAPSHOT ==========
        $invoiceAmount = (float) $invoice->amount;
        $amountPaid    = (float) $invoice->amount_paid;
        $balance       = max(0, $invoiceAmount - $amountPaid);

        // ========== STATE FLAGS ==========
        $isOverdue = $invoice->status === 'overdue';
        $isVoid    = $invoice->status === 'void';
        $isPaid    = $invoice->status === 'paid';

        // ========== ✅ FIX: display values reflect the status ==========
        // Fully Paid → show the amount paid (which equals the invoice total)
        // Partial / Pending / Overdue → show the outstanding balance
        // Void → show zero, greyed
        if ($isVoid) {
            $displayLabel = 'Voided Invoice';
            $displayValue = 0.0;
            $displayColor = '#383d41';   // grey
        } elseif ($isPaid) {
            $displayLabel = 'Amount Paid in Full';
            $displayValue = $amountPaid;
            $displayColor = '#27ae60';   // green
        } else {
            $displayLabel = 'Balance Due';
            $displayValue = $balance;
            $displayColor = $isOverdue ? '#e74c3c' : '#1a2332';   // red if overdue
        }

        // ========== STATUS LABEL ==========
        $statusLabel = match ($invoice->status) {
            'pending' => 'Pending',
            'partial' => 'Partial',
            'paid'    => 'Paid',
            'overdue' => 'Overdue',
            'void'    => 'Void',
            default   => ucfirst($invoice->status),
        };
    @endphp

    {{-- ============================================================
         HEADER
         ============================================================ --}}
    <div class="doc-header">
        <div class="brand">{{ config('app.name', 'Property Management System') }}</div>
        <h1>INVOICE</h1>
        <h2>{{ $property->property_name ?? 'N/A' }} — Unit {{ $unit->unit_number }}</h2>

        <div class="doc-meta">
            <span class="meta-item">
                <strong>Reference:</strong> {{ $invoice->reference }}
            </span>
            <span class="meta-item">
                <strong>Issued:</strong> {{ optional($invoice->issue_date)->format('F j, Y') ?? '—' }}
            </span>
            <span class="meta-item">
                <strong>Due:</strong> {{ optional($invoice->due_date)->format('F j, Y') ?? '—' }}
            </span>
            <span class="meta-item">
                <strong>Currency:</strong> {{ $currencySymbol }}
            </span>
        </div>

        <div class="mt-10">
            <span class="status-badge status-{{ $invoice->status }}">
                {{ $statusLabel }}
            </span>
        </div>
    </div>

    {{-- ============================================================
         ✅ FIXED: AMOUNT SUMMARY
         ============================================================ --}}
    <div class="amount-box">
        <div class="label">{{ $displayLabel }}</div>

        <div class="value" style="color: {{ $displayColor }};">
            {{ $currencySymbol }} {{ number_format($displayValue, 2) }}
        </div>

        {{-- Context sub-line --}}
        @if($isPaid)
            <div class="sub-line">
                Paid on {{ optional($invoice->paid_at)->format('F j, Y') ?? '—' }}
            </div>
        @elseif(!$isVoid && $amountPaid > 0 && $balance > 0)
            <div class="sub-line">
                {{ $currencySymbol }} {{ number_format($amountPaid, 2) }}
                of {{ $currencySymbol }} {{ number_format($invoiceAmount, 2) }} already paid
            </div>
        @endif
    </div>

    {{-- ============================================================
         FINANCIAL SUMMARY
         ============================================================ --}}
    <table class="summary-table">
        <tr>
            <td>Total Invoice Amount</td>
            <td>{{ $currencySymbol }} {{ number_format($invoiceAmount, 2) }}</td>
        </tr>
        <tr>
            <td>Amount Paid</td>
            <td class="text-success">{{ $currencySymbol }} {{ number_format($amountPaid, 2) }}</td>
        </tr>
        <tr class="total">
            <td>Remaining Balance</td>
            <td class="{{ $balance > 0 ? 'text-danger' : 'text-success' }}">
                {{ $currencySymbol }} {{ number_format($balance, 2) }}
            </td>
        </tr>
    </table>

    {{-- ============================================================
         INVOICE DETAILS
         ============================================================ --}}
    <div class="section">
        <div class="section-title">Invoice Details</div>
        <table class="info-table">
            <tr>
                <th>Invoice Type</th>
                <td>{{ ucfirst(str_replace('_', ' ', $invoice->invoice_type)) }}</td>
            </tr>
            <tr>
                <th>Description</th>
                <td>{{ $invoice->description }}</td>
            </tr>
            @if($invoice->period_start || $invoice->period_end)
            <tr>
                <th>Service Period</th>
                <td>
                    {{ optional($invoice->period_start)->format('F j, Y') ?? '—' }}
                    &nbsp;–&nbsp;
                    {{ optional($invoice->period_end)->format('F j, Y') ?? '—' }}
                </td>
            </tr>
            @endif
            @if($invoice->lease_id)
            <tr>
                <th>Lease Reference</th>
                <td>#{{ $invoice->lease_id }}</td>
            </tr>
            @endif
        </table>
    </div>

    {{-- ============================================================
         PARTIES
         ============================================================ --}}
    <div class="section">
        <div class="section-title">Parties</div>
        <table class="info-table">
            @if($tenant)
            <tr>
                <th>Tenant</th>
                <td>
                    <strong>{{ $tenant->name }}</strong><br>
                    @if($tenant->email) <span class="text-muted">Email: {{ $tenant->email }}</span><br> @endif
                    @if($tenant->phone) <span class="text-muted">Phone: {{ $tenant->phone }}</span> @endif
                </td>
            </tr>
            @endif
            @if($landlord)
            <tr>
                <th>Landlord</th>
                <td>
                    <strong>{{ $landlord->name }}</strong><br>
                    @if($landlord->email) <span class="text-muted">Email: {{ $landlord->email }}</span><br> @endif
                    @if($landlord->phone) <span class="text-muted">Phone: {{ $landlord->phone }}</span> @endif
                </td>
            </tr>
            @endif
            @if($property)
            <tr>
                <th>Property</th>
                <td>
                    <strong>{{ $property->property_name }}</strong><br>
                    @if($property->address) <span class="text-muted">{{ $property->address }}</span><br> @endif
                    <span class="text-muted">Unit: {{ $unit->unit_number }}</span>
                </td>
            </tr>
            @endif
        </table>
    </div>

    {{-- ============================================================
         PAYMENT INFORMATION
         ============================================================ --}}
    @if($amountPaid > 0 && !$isVoid)
    <div class="section">
        <div class="section-title">Payment Information</div>
        <table class="info-table">
            @if($invoice->last_payment_at)
            <tr>
                <th>Last Payment Date</th>
                <td>{{ optional($invoice->last_payment_at)->format('F j, Y') }}</td>
            </tr>
            @endif
            @if($invoice->payment_method)
            <tr>
                <th>Payment Method</th>
                <td>{{ ucfirst(str_replace('_', ' ', $invoice->payment_method)) }}</td>
            </tr>
            @endif
            @if($invoice->payment_reference)
            <tr>
                <th>Payment Reference</th>
                <td>{{ $invoice->payment_reference }}</td>
            </tr>
            @endif
            @if($invoice->paid_at)
            <tr>
                <th>Fully Paid On</th>
                <td class="text-success">{{ optional($invoice->paid_at)->format('F j, Y H:i') }}</td>
            </tr>
            @endif
        </table>
    </div>
    @endif

    {{-- ============================================================
         NOTES
         ============================================================ --}}
    @if($invoice->notes)
    <div class="section">
        <div class="section-title">Notes</div>
        <div class="notes-box">{{ $invoice->notes }}</div>
    </div>
    @endif

    {{-- ============================================================
         VOID REASON (if voided)
         ============================================================ --}}
    @if($isVoid)
    <div class="section">
        <div class="section-title">Void Information</div>
        <div class="void-box">
            <strong>Voided On:</strong> {{ optional($invoice->voided_at)->format('F j, Y H:i') ?? '—' }}<br>
            <strong>Reason:</strong> {{ $invoice->void_reason ?? 'No reason recorded' }}
        </div>
    </div>
    @endif

    {{-- ============================================================
         LEGAL NOTICE
         ============================================================ --}}
    <div class="section">
        <div class="section-title">Legal Notice</div>
        <p style="font-size: 10px; color: #54637a; margin: 0;">
            This invoice is issued under and governed by the
            <strong>{{ $governingLaw }}</strong> and applicable Ghanaian law.
            All amounts are denominated in {{ $currencySymbol }}.
            Please retain this document for your records.
        </p>
    </div>

    {{-- ============================================================
         FOOTER
         ============================================================ --}}
    <div class="doc-footer">
        <div>
            Invoice {{ $invoice->reference }} ·
            {{ $property->property_name ?? '' }} — Unit {{ $unit->unit_number }} ·
            Generated {{ $generated_date }}
        </div>
        <div>
            Page <span class="page-number"></span> ·
            This is a computer-generated document · Governed by the {{ $governingLaw }}
        </div>
    </div>
</body>
</html>