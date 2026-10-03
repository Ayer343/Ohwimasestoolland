<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $invoice->invoice_number ?? 'INV-'.str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }} - {{ $settings->system_name ?? config('app.name') }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* A5 Size Styles - Optimized */
        @page {
            size: A5 landscape;
            margin: 0.5cm;
        }
        
        @media print {
            body {
                width: 210mm;
                height: 148mm;
                margin: 0;
                padding: 0.5cm;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                color: #2d3748;
                background: white;
                font-size: 9pt;
                line-height: 1.2;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
            .print-container {
                width: 100%;
                height: 100%;
                margin: 0 auto;
                position: relative;
            }
            .payment-instructions, .bulk-coverage-section {
                page-break-inside: avoid;
            }
            
            /* Compact spacing for A5 */
            .mb-1 { margin-bottom: 0.15cm; }
            .mb-2 { margin-bottom: 0.25cm; }
            .mb-3 { margin-bottom: 0.35cm; }
            .mt-1 { margin-top: 0.15cm; }
            .mt-2 { margin-top: 0.25cm; }
            .p-1 { padding: 0.15cm; }
            .p-2 { padding: 0.25cm; }
        }

        /* Screen Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .no-print {
            text-align: center;
            margin-bottom: 20px;
            padding: 20px;
        }

        .print-container {
            background: white;
            width: 210mm;
            height: 148mm;
            padding: 0.5cm;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            position: relative;
            overflow: hidden;
        }

        /* Header Styles - Compact */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.3cm;
            padding-bottom: 0.2cm;
            border-bottom: 2px solid #667eea;
        }

        .company-info {
            flex: 1;
        }

        .company-name {
            font-size: 14pt;
            font-weight: bold;
            color: #2d3748;
            margin: 0;
        }

        .company-tagline {
            font-size: 7pt;
            color: #718096;
            margin: 2px 0;
        }

        .invoice-title {
            text-align: right;
            flex: 1;
        }

        .invoice-title h1 {
            font-size: 18pt;
            font-weight: 800;
            color: #667eea;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .invoice-title .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 7pt;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .badge-paid { background: #c6f6d5; color: #22543d; }
        .badge-pending { background: #feebc8; color: #744210; }
        .badge-overdue { background: #fed7d7; color: #742a2a; }
        .badge-consolidated { background: #e9d8fd; color: #553c9a; }
        .badge-bulk { background: #667eea; color: white; margin-left: 4px; }

        /* Invoice Details - Compact Grid */
        .invoice-details {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.2cm;
            margin-bottom: 0.3cm;
            padding: 0.2cm;
            background: #f7fafc;
            border-radius: 4px;
            border-left: 3px solid #667eea;
            font-size: 8pt;
        }

        .detail-group h3 {
            font-size: 7pt;
            font-weight: 600;
            color: #718096;
            margin: 0 0 2px 0;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .detail-value {
            font-size: 8pt;
            font-weight: 600;
            color: #2d3748;
            margin: 0;
        }

        /* Property Information - Compact */
        .property-section {
            margin-bottom: 0.3cm;
            padding: 0.2cm;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
            color: white;
            font-size: 8pt;
        }

        .property-section h2 {
            font-size: 9pt;
            font-weight: 600;
            margin: 0 0 3px 0;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .property-address {
            font-size: 10pt;
            font-weight: bold;
            margin: 0 0 3px 0;
        }

        .property-details {
            font-size: 7pt;
            opacity: 0.9;
            margin: 1px 0;
        }

        /* Bulk Coverage Section - FIXED with decoded periods */
        .bulk-coverage-section {
            margin-bottom: 0.3cm;
            padding: 0.2cm;
            background: #f0fff4;
            border-radius: 4px;
            border-left: 3px solid #48bb78;
            font-size: 7pt;
        }

        .bulk-coverage-section h3 {
            font-size: 8pt;
            font-weight: 600;
            color: #22543d;
            margin: 0 0 3px 0;
            text-transform: uppercase;
        }

        .coverage-months {
            display: flex;
            flex-wrap: wrap;
            gap: 2px;
            margin-top: 2px;
        }

        .coverage-month {
            padding: 1px 4px;
            background: rgba(72, 187, 120, 0.2);
            border-radius: 10px;
            color: #22543d;
            font-size: 6pt;
            font-weight: 600;
        }

        /* Items Table - Compact */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0.3cm 0;
            font-size: 7pt;
        }

        .items-table th {
            background: #667eea;
            color: white;
            padding: 0.15cm;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-size: 7pt;
        }

        .items-table td {
            padding: 0.15cm;
            border-bottom: 1px solid #e2e8f0;
        }

        .items-table tr:last-child td {
            border-bottom: none;
        }

        .total-row {
            background: #f7fafc;
            font-weight: bold;
        }

        .total-row td {
            padding: 0.2cm 0.15cm;
            font-size: 9pt;
            color: #2d3748;
        }

        .discount-row {
            color: #48bb78;
        }

        .penalty-row {
            color: #f56565;
        }

        /* Payment Info */
        .payment-info {
            margin-top: 0.2cm;
            padding: 0.2cm;
            background: #f0fff4;
            border-radius: 4px;
            border-left: 3px solid #48bb78;
        }

        .payment-info h3 {
            font-size: 8pt;
            font-weight: 600;
            color: #22543d;
            margin: 0 0 3px 0;
            text-transform: uppercase;
        }

        .payment-details {
            font-size: 7pt;
            color: #2d3748;
        }

        .payment-details p {
            margin: 2px 0;
        }

        /* Payment Instructions */
        .payment-instructions {
            margin-top: 0.2cm;
            padding: 0.2cm;
            background: #ebf8ff;
            border-radius: 4px;
            border-left: 3px solid #4299e1;
        }

        .payment-instructions h3 {
            font-size: 8pt;
            font-weight: 600;
            color: #2b6cb0;
            margin: 0 0 3px 0;
            text-transform: uppercase;
        }

        .instructions-content {
            font-size: 6pt;
            color: #2d3748;
        }

        .instructions-content p {
            margin: 1px 0;
        }

        /* Notes */
        .notes-section {
            margin-top: 0.2cm;
            padding: 0.2cm;
            background: #fffaf0;
            border-radius: 4px;
            border-left: 3px solid #ed8936;
        }

        .notes-section h3 {
            font-size: 8pt;
            font-weight: 600;
            color: #744210;
            margin: 0 0 3px 0;
            text-transform: uppercase;
        }

        .notes-content {
            font-size: 7pt;
            color: #2d3748;
            font-style: italic;
        }

        /* Footer */
        .footer {
            margin-top: 0.3cm;
            padding-top: 0.2cm;
            border-top: 1px solid #e2e8f0;
            text-align: center;
        }

        .footer p {
            font-size: 6pt;
            color: #718096;
            margin: 1px 0;
        }

        .footer .generated {
            font-weight: 600;
        }

        /* Buttons */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            margin: 3px;
            font-size: 9pt;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
        }

        .btn-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }

        /* QR Code Section */
        .qr-section {
            position: absolute;
            bottom: 0.3cm;
            right: 0.3cm;
            text-align: center;
        }

        .qr-placeholder {
            width: 0.6cm;
            height: 0.6cm;
            background: #f7fafc;
            border: 1px dashed #cbd5e0;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.3cm;
            color: #a0aec0;
        }

        .qr-text {
            font-size: 5pt;
            color: #718096;
            margin-top: 2px;
        }

        /* Watermark */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            opacity: 0.03;
            font-size: 40pt;
            font-weight: 800;
            color: #000;
            pointer-events: none;
            user-select: none;
            white-space: nowrap;
        }

        /* Child Invoices Table - NEW */
        .child-invoices {
            margin-top: 0.2cm;
            font-size: 6pt;
        }

        .child-invoices h3 {
            font-size: 7pt;
            font-weight: 600;
            margin: 0 0 2px 0;
        }

        .child-invoices table {
            width: 100%;
            border-collapse: collapse;
        }

        .child-invoices th {
            background: #e2e8f0;
            padding: 0.1cm;
            text-align: left;
            font-size: 6pt;
        }

        .child-invoices td {
            padding: 0.1cm;
            border-bottom: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    @php
        // ✅ FIX: Properly decode covers_periods from JSON string to array
        $coveragePeriods = [];
        $coversPeriodsRaw = $invoice->covers_periods;
        
        if (!empty($coversPeriodsRaw)) {
            if (is_string($coversPeriodsRaw)) {
                $coveragePeriods = json_decode($coversPeriodsRaw, true);
                if (!is_array($coveragePeriods)) {
                    $coveragePeriods = [];
                }
            } elseif (is_array($coversPeriodsRaw)) {
                $coveragePeriods = $coversPeriodsRaw;
            }
        }
        
        // If still empty, try to generate from bulk_coverage_start/end
        if (empty($coveragePeriods) && $invoice->bulk_coverage_start && $invoice->bulk_coverage_end) {
            $start = \Carbon\Carbon::parse($invoice->bulk_coverage_start . '-01');
            $end = \Carbon\Carbon::parse($invoice->bulk_coverage_end . '-01');
            $current = clone $start;
            while ($current <= $end) {
                $coveragePeriods[] = $current->format('Y-m');
                $current->addMonth();
            }
        }
        
        $formattedCoveragePeriods = array_map(function($p) {
            try {
                return \Carbon\Carbon::parse($p . '-01')->format('M Y');
            } catch (\Exception $e) {
                return $p;
            }
        }, $coveragePeriods);
        
        $hasActiveCoverage = $invoice->is_bulk_payment && $invoice->isPaid() && !empty($coveragePeriods);
        
        // Get child invoices for display
        $childInvoices = $invoice->childInvoices ?? collect();
    @endphp

    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print"></i> Print Invoice
        </button>
        <button onclick="closeWindow()" class="btn btn-secondary">
            <i class="fas fa-times"></i> Close
        </button>
        <button onclick="downloadAsPDF()" class="btn btn-success">
            <i class="fas fa-download"></i> Download PDF
        </button>
    </div>

    <div class="print-container">
        <!-- Watermark -->
        <div class="watermark">{{ $settings->system_name ?? config('app.name') }}</div>

        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <h1 class="company-name">{{ $settings->system_name ?? config('app.name', 'Property Management') }}</h1>
                <p class="company-tagline">
                    @if($settings->system_phone)
                    <i class="fas fa-phone"></i> {{ $settings->system_phone }}
                    @endif
                    @if($settings->system_email)
                     • <i class="fas fa-envelope"></i> {{ $settings->system_email }}
                    @endif
                </p>
            </div>
            <div class="invoice-title">
                <h1>INVOICE</h1>
                <div>
                    <span class="badge badge-{{ $invoice->status }}">
                        {{ $invoice->status == 'consolidated' ? 'IN BULK' : strtoupper($invoice->status) }}
                    </span>
                    @if($invoice->is_bulk_payment)
                    <span class="badge badge-bulk">
                        <i class="fas fa-layer-group"></i> BULK
                    </span>
                    @endif
                    @if($hasActiveCoverage)
                    <span class="badge badge-paid">
                        <i class="fas fa-shield-alt"></i> COVERAGE
                    </span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Invoice Details - Compact 4-column -->
        <div class="invoice-details">
            <div class="detail-group">
                <h3>Invoice #</h3>
                <p class="detail-value">{{ $invoice->invoice_number ?? 'INV-'.str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</p>
            </div>
            <div class="detail-group">
                <h3>Period</h3>
                <p class="detail-value">
                    @php
                        if($invoice->is_bulk_payment) {
                            if($invoice->bulk_start_month && $invoice->bulk_end_month) {
                                echo \Carbon\Carbon::parse($invoice->bulk_start_month . '-01')->format('M Y') . ' - ' . 
                                     \Carbon\Carbon::parse($invoice->bulk_end_month . '-01')->format('M Y');
                            } else {
                                echo $invoice->bulk_months . ' Month Bulk';
                            }
                        } else {
                            try {
                                echo \Carbon\Carbon::parse($invoice->period . '-01')->format('M Y');
                            } catch (\Exception $e) {
                                echo $invoice->period;
                            }
                        }
                    @endphp
                </p>
            </div>
            <div class="detail-group">
                <h3>Issue Date</h3>
                <p class="detail-value">{{ $invoice->created_at->format('d/m/Y') }}</p>
            </div>
            <div class="detail-group">
                <h3>Due Date</h3>
                <p class="detail-value">{{ $invoice->due_date->format('d/m/Y') }}</p>
            </div>
        </div>

        <!-- Property Information -->
        <div class="property-section">
            <h2>Property Information</h2>
            <p class="property-address">
                {{ $invoice->property->house_number }} {{ $invoice->property->street_name }}
            </p>
            <p class="property-details">
                @if($invoice->property->zone)<i class="fas fa-map-marker-alt"></i> {{ $invoice->property->zone }}@endif
                @if($invoice->property->section) - {{ $invoice->property->section }}@endif
                @if($invoice->property->block_number) • Block {{ $invoice->property->block_number }}@endif
            </p>
            <p class="property-details">
                <i class="fas fa-user"></i> {{ $invoice->property->landlord->name }} • 
                <i class="fas fa-phone"></i> {{ $invoice->property->landlord->phone }}
            </p>
        </div>

        <!-- Bulk Coverage Section (if active) - FIXED with decoded periods -->
        @if($hasActiveCoverage)
        <div class="bulk-coverage-section">
            <h3><i class="fas fa-shield-alt"></i> Active Bulk Coverage</h3>
            <div class="coverage-months">
                @foreach($formattedCoveragePeriods as $period)
                <span class="coverage-month">{{ $period }}</span>
                @endforeach
            </div>
            <p style="margin-top: 2px; font-size: 6pt;">
                <i class="fas fa-check-circle"></i> No further invoices will be generated for these months
            </p>
        </div>
        @endif

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @if($invoice->is_bulk_payment && $childInvoices->count() > 0)
                    @foreach($childInvoices->take(3) as $child)
                    <tr>
                        <td>Monthly Dues - 
                            @php
                                try {
                                    echo \Carbon\Carbon::parse($child->period . '-01')->format('M Y');
                                } catch (\Exception $e) {
                                    echo $child->period;
                                }
                            @endphp
                        </td>
                        <td style="text-align: right;">{{ $settings->formatAmount($child->amount) }}</td>
                    </tr>
                    @endforeach
                    @if($childInvoices->count() > 3)
                    <tr>
                        <td colspan="2" style="text-align: center; font-style: italic; color: #718096;">
                            + {{ $childInvoices->count() - 3 }} more months
                        </td>
                    </tr>
                    @endif
                @elseif($invoice->is_bulk_payment)
                    <tr>
                        <td>Bulk Payment - {{ $invoice->bulk_months }} Months</td>
                        <td style="text-align: right;">{{ $settings->formatAmount($invoice->amount) }}</td>
                    </tr>
                @elseif($invoice->bulk_parent_id)
                    <tr>
                        <td>Monthly Dues - 
                            @php
                                try {
                                    echo \Carbon\Carbon::parse($invoice->period . '-01')->format('M Y');
                                } catch (\Exception $e) {
                                    echo $invoice->period;
                                }
                            @endphp
                            <span style="color: #718096; font-style: italic;"> (Consolidated)</span>
                        </td>
                        <td style="text-align: right;">{{ $settings->formatAmount($invoice->amount) }}</td>
                    </tr>
                @else
                    <tr>
                        <td>Monthly Dues - 
                            @php
                                try {
                                    echo \Carbon\Carbon::parse($invoice->period . '-01')->format('F Y');
                                } catch (\Exception $e) {
                                    echo $invoice->period;
                                }
                            @endphp
                        </td>
                        <td style="text-align: right;">{{ $settings->formatAmount($invoice->amount) }}</td>
                    </tr>
                @endif

                <!-- Discount if applicable -->
                @if($invoice->discount_amount > 0)
                <tr class="discount-row">
                    <td>Discount ({{ $invoice->discount_percentage }}%)</td>
                    <td style="text-align: right;">-{{ $settings->formatAmount($invoice->discount_amount) }}</td>
                </tr>
                @endif

                <!-- Penalty if applicable -->
                @if($invoice->penalty_amount > 0)
                <tr class="penalty-row">
                    <td>Late Fee Penalty</td>
                    <td style="text-align: right;">+{{ $settings->formatAmount($invoice->penalty_amount) }}</td>
                </tr>
                @endif

                <!-- Total Row -->
                <tr class="total-row">
                    <td>TOTAL AMOUNT DUE</td>
                    <td style="text-align: right; color: #667eea; font-size: 11pt;">
                        {{ $settings->formatAmount($invoice->total_amount ?? ($invoice->amount + ($invoice->penalty_amount ?? 0) - ($invoice->discount_amount ?? 0))) }}
                    </td>
                </tr>

                <!-- Paid/Balance if applicable -->
                @if($invoice->paid_amount > 0)
                <tr>
                    <td>Paid Amount</td>
                    <td style="text-align: right; color: #48bb78;">{{ $settings->formatAmount($invoice->paid_amount) }}</td>
                </tr>
                @endif
                
                @if($invoice->balance > 0)
                <tr>
                    <td><strong>BALANCE DUE</strong></td>
                    <td style="text-align: right; color: #f56565; font-weight: bold;">
                        {{ $settings->formatAmount($invoice->balance) }}
                    </td>
                </tr>
                @endif
            </tbody>
        </table>

        <!-- Child Invoices Summary (if bulk with children) -->
        @if($invoice->is_bulk_payment && $childInvoices->count() > 3)
        <div class="child-invoices">
            <h3>Included Months:</h3>
            <div style="display: flex; flex-wrap: wrap; gap: 2px;">
                @foreach($childInvoices as $child)
                <span style="padding: 1px 4px; background: #e2e8f0; border-radius: 8px; font-size: 5pt;">
                    @php
                        try {
                            echo \Carbon\Carbon::parse($child->period . '-01')->format('M y');
                        } catch (\Exception $e) {
                            echo $child->period;
                        }
                    @endphp
                </span>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Payment Information (if paid) -->
        @if($invoice->status === 'paid' && $invoice->payment_date)
        <div class="payment-info">
            <h3><i class="fas fa-check-circle"></i> Payment Received</h3>
            <div class="payment-details">
                <p><strong>Date:</strong> {{ \Carbon\Carbon::parse($invoice->payment_date)->format('d/m/Y') }} | 
                   <strong>Method:</strong> {{ ucfirst(str_replace('_', ' ', $invoice->payment_method)) }}</p>
                @if($invoice->payment_reference)
                <p><strong>Ref:</strong> {{ $invoice->payment_reference }}</p>
                @endif
            </div>
        </div>
        @endif

        <!-- Payment Instructions (if not paid) -->
        @if($invoice->status !== 'paid' && $invoice->status !== 'cancelled' && $invoice->status !== 'consolidated')
        <div class="payment-instructions">
            <h3><i class="fas fa-info-circle"></i> Payment Instructions</h3>
            <div class="instructions-content">
                @if($settings->payment_mobile_number)
                <p><strong>Mobile Money:</strong> {{ $settings->payment_mobile_number }} 
                    @if($settings->payment_account_name)({{ $settings->payment_account_name }})@endif
                </p>
                @endif
                @if($settings->bank_account_number)
                <p><strong>Bank Transfer:</strong> {{ $settings->bank_account_number }}</p>
                @endif
                <p><strong>Reference:</strong> INV-{{ str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</p>
                <p style="color: #e53e3e; font-weight: bold;">
                    Due: {{ $invoice->due_date->format('d/m/Y') }}
                </p>
            </div>
        </div>
        @endif

        <!-- Notes -->
        @if($invoice->notes)
        <div class="notes-section">
            <h3><i class="fas fa-sticky-note"></i> Notes</h3>
            <div class="notes-content">
                {{ $invoice->notes }}
            </div>
        </div>
        @endif

        <!-- Bulk Parent Link (if consolidated) -->
        @if($invoice->bulk_parent_id)
        <div style="margin-top: 0.1cm; font-size: 6pt; text-align: center; color: #718096;">
            <i class="fas fa-link"></i> Part of Bulk Invoice #INV-{{ str_pad($invoice->bulk_parent_id, 6, '0', STR_PAD_LEFT) }}
        </div>
        @endif

        <!-- QR Code -->
        <div class="qr-section">
            <div class="qr-placeholder">
                <i class="fas fa-qrcode"></i>
            </div>
            <div class="qr-text">Scan to Pay</div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p class="generated">Generated: {{ now()->format('d/m/Y H:i') }}</p>
            <p>{{ $settings->system_name ?? config('app.name') }} • {{ $invoice->creator->name ?? 'System' }}</p>
            <p>Thank you for your partnership!</p>
        </div>
    </div>

    <script>
        // Close window function
        function closeWindow() {
            // Try to close the window
            window.close();
            
            // If window.close() doesn't work (some browsers block it), 
            // show a message and redirect back
            setTimeout(function() {
                alert('If the window doesn\'t close automatically, you can close it manually.');
                // Optionally redirect back to invoices page
                window.location.href = '{{ route("landlord.invoices") }}';
            }, 100);
        }
        
        // Download as PDF function
        function downloadAsPDF() {
            // Method 1: Use window.print() with save as PDF
            // This opens the print dialog where user can select "Save as PDF"
            window.print();
            
            // Alternative: You could also use a server-side PDF generation
            // Uncomment below to use server-side PDF generation if available
            /*
            const invoiceId = {{ $invoice->id }};
            window.open(`/landlord/invoices/${invoiceId}/export-pdf`, '_blank');
            */
        }
        
        // Auto-print on load (optional - uncomment if you want automatic print)
        // window.onload = function() { 
        //     setTimeout(function() {
        //         window.print();
        //     }, 500);
        // }
        
        // Close window after print (optional)
        window.onafterprint = function() {
            // Uncomment to auto-close after printing
            // setTimeout(() => { window.close(); }, 1000);
        };
        
        // Handle ESC key to close
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeWindow();
            }
        });
    </script>
</body>
</html>