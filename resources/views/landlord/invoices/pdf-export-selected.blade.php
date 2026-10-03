<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Selected Invoices - {{ now()->format('F Y') }}</title>
    
    <!-- ============ FAVICON ============ -->
    @php
        $settings = \App\Models\SystemSetting::getSettings();
    @endphp
    @if($settings->hasFavicon())
        <link rel="icon" href="{{ $settings->getFaviconUrl() }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ $settings->getFaviconUrl() }}" type="image/x-icon">
        <link rel="apple-touch-icon" href="{{ $settings->getFaviconUrl() }}">
        <!-- Additional favicon sizes for better browser support -->
        <link rel="icon" type="image/png" sizes="16x16" href="{{ $settings->getFaviconUrl() }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ $settings->getFaviconUrl() }}">
        <link rel="icon" type="image/png" sizes="64x64" href="{{ $settings->getFaviconUrl() }}">
        <!-- Microsoft Edge Tile -->
        <meta name="msapplication-TileImage" content="{{ $settings->getFaviconUrl() }}">
        <meta name="msapplication-TileColor" content="#10B981">
    @else
        <!-- Default favicon fallback -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <meta name="msapplication-TileColor" content="#10B981">
    @endif
    
    <style>
        body {
            font-family: 'DejaVu Sans', 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 20px;
            color: #333;
        }
        
        /* Header Styles */
        .invoice-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #10B981;
            padding-bottom: 15px;
        }
        
        .invoice-header h1 {
            margin: 0;
            font-size: 28px;
            color: #10B981;
        }
        
        .invoice-header p {
            margin: 5px 0 0;
            color: #666;
        }
        
        /* Company Info */
        .company-info {
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            font-size: 11px;
            color: #666;
        }
        
        /* Selection Summary */
        .selection-summary {
            margin-bottom: 20px;
            padding: 12px;
            background-color: #f0fdf4;
            border-left: 3px solid #10B981;
            border-radius: 6px;
        }
        
        .selection-summary strong {
            color: #166534;
        }
        
        /* Invoice Card */
        .invoice-card {
            margin-bottom: 25px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            page-break-inside: avoid;
        }
        
        .invoice-card-header {
            background: #f9fafb;
            padding: 12px 15px;
            border-bottom: 2px solid #10B981;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .invoice-number {
            font-size: 14px;
            font-weight: bold;
            color: #10B981;
        }
        
        .invoice-status {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .invoice-body {
            padding: 15px;
        }
        
        /* Info Grid */
        .info-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .info-section {
            flex: 1;
            min-width: 200px;
        }
        
        .info-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #6B7280;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 13px;
            font-weight: 500;
            color: #111827;
        }
        
        /* Amounts Table */
        .amounts-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .amounts-table td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .amounts-table .label {
            font-weight: bold;
            color: #6B7280;
        }
        
        .amounts-table .value {
            text-align: right;
            font-weight: bold;
        }
        
        .total-row {
            background: #f9fafb;
        }
        
        .total-row td {
            border-top: 2px solid #e5e7eb;
            font-size: 14px;
        }
        
        /* Coverage Info */
        .coverage-info {
            margin-top: 12px;
            padding: 10px;
            background: #e0e7ff;
            border-radius: 6px;
            font-size: 10px;
        }
        
        .payment-instructions {
            margin-top: 12px;
            padding: 10px;
            background: #fef3c7;
            border-radius: 6px;
            font-size: 10px;
        }
        
        /* Status Badges */
        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-pending {
            background: #fed7aa;
            color: #92400e;
        }
        
        .status-overdue {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-consolidated {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            font-size: 9px;
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            color: #9CA3AF;
        }
        
        /* Utilities */
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .font-bold {
            font-weight: bold;
        }
        
        .warning {
            color: #dc2626;
        }
        
        .success {
            color: #10B981;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="invoice-header">
        <h1>{{ $settings->system_name ?? 'Property Management System' }}</h1>
        <p>SELECTED INVOICES REPORT</p>
        <p>Generated: {{ now()->format('F d, Y H:i:s') }}</p>
    </div>
    
    <!-- Company Information -->
    <div class="company-info">
        @if($settings->system_email) Email: {{ $settings->system_email }} @endif
        @if($settings->system_phone) | Phone: {{ $settings->system_phone }} @endif
        @if($settings->system_address) | Address: {{ $settings->system_address }} @endif
    </div>
    
    <!-- Selection Summary -->
    <div class="selection-summary">
        <strong>📄 Selected Invoices Summary</strong><br>
        <span>Total Invoices: {{ $invoices->count() }}</span> | 
        <span>Total Amount: {{ $settings->safeFormatAmount($invoices->sum('total_amount')) }}</span> |
        <span>Generated For: {{ auth()->user()->name }}</span>
    </div>
    
    <!-- Individual Invoice Cards -->
    @foreach($invoices as $invoice)
    @php
        $isCovered = false;
        if(isset($bulkCoverages) && isset($bulkCoverages[$invoice->property_id])) {
            foreach($bulkCoverages[$invoice->property_id] as $coverage) {
                if(in_array($invoice->period, $coverage['periods'] ?? [])) {
                    $isCovered = true;
                    $coveringBulk = $coverage;
                    break;
                }
            }
        }
        
        $periodDisplay = $invoice->period;
        if($invoice->is_bulk_payment && $invoice->bulk_coverage_start && $invoice->bulk_coverage_end) {
            $periodDisplay = \Carbon\Carbon::parse($invoice->bulk_coverage_start . '-01')->format('M Y') . ' - ' . 
                            \Carbon\Carbon::parse($invoice->bulk_coverage_end . '-01')->format('M Y');
        } elseif(preg_match('/^\d{4}-\d{2}$/', $invoice->period)) {
            $periodDisplay = \Carbon\Carbon::parse($invoice->period . '-01')->format('F Y');
        }
        
        $statusClass = match($invoice->status) {
            'paid' => 'status-paid',
            'pending' => 'status-pending',
            'overdue' => 'status-overdue',
            'consolidated' => 'status-consolidated',
            default => ''
        };
    @endphp
    
    <div class="invoice-card">
        <div class="invoice-card-header">
            <div>
                <div class="invoice-number">{{ $invoice->invoice_number ?? 'INV-'.str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</div>
                <div style="font-size: 9px; color: #6B7280; margin-top: 3px;">
                    {{ $periodDisplay }}
                </div>
            </div>
            <div>
                @if($isCovered && $invoice->status != 'paid')
                    <span class="invoice-status" style="background: #d1fae5; color: #065f46;">
                        <i class="fas fa-shield-alt"></i> Covered by Bulk
                    </span>
                @else
                    <span class="invoice-status {{ $statusClass }}">
                        {{ $invoice->status == 'consolidated' ? 'In Bulk' : ucfirst($invoice->status) }}
                    </span>
                @endif
            </div>
        </div>
        
        <div class="invoice-body">
            <!-- Property and Landlord Info -->
            <div class="info-grid">
                <div class="info-section">
                    <div class="info-label">Property Details</div>
                    <div class="info-value">
                        @if($invoice->property)
                            <strong>{{ $invoice->property->property_name ?? '' }}</strong><br>
                            {{ $invoice->property->house_number ?? '#' }} {{ $invoice->property->street_name }}<br>
                            @if($invoice->property->block_number)
                                Block: {{ $invoice->property->block_number }}<br>
                            @endif
                            @if($invoice->property->zone)
                                Zone: {{ $invoice->property->zone }}
                            @endif
                        @else
                            Property not found
                        @endif
                    </div>
                </div>
                
                <div class="info-section">
                    <div class="info-label">Landlord Information</div>
                    <div class="info-value">
                        {{ auth()->user()->name }}<br>
                        @if(auth()->user()->email)
                            Email: {{ auth()->user()->email }}<br>
                        @endif
                        @if(auth()->user()->phone)
                            Phone: {{ auth()->user()->phone }}
                        @endif
                    </div>
                </div>
                
                <div class="info-section">
                    <div class="info-label">Billing Information</div>
                    <div class="info-value">
                        <div>Due Date: {{ \Carbon\Carbon::parse($invoice->due_date)->format('F d, Y') }}</div>
                        @if($invoice->payment_date)
                            <div>Paid Date: {{ \Carbon\Carbon::parse($invoice->payment_date)->format('F d, Y') }}</div>
                        @endif
                        @if($invoice->payment_method)
                            <div>Payment Method: {{ ucfirst(str_replace('_', ' ', $invoice->payment_method)) }}</div>
                        @endif
                        @if($invoice->payment_reference)
                            <div>Reference: {{ $invoice->payment_reference }}</div>
                        @endif
                    </div>
                </div>
            </div>
            
            <!-- Amounts Breakdown -->
            <table class="amounts-table">
                 <tr>
                    <td class="label">Monthly Dues</td>
                    <td class="value">{{ $settings->safeFormatAmount($invoice->amount) }}</td>
                 </tr>
                @if($invoice->penalty_amount > 0)
                 <tr>
                    <td class="label">Late Payment Penalty</td>
                    <td class="value warning">{{ $settings->safeFormatAmount($invoice->penalty_amount) }}</td>
                 </tr>
                @endif
                @if($invoice->discount_amount > 0)
                 <tr>
                    <td class="label">Discount Applied</td>
                    <td class="value success">-{{ $settings->safeFormatAmount($invoice->discount_amount) }}</td>
                 </tr>
                @endif
                @if($invoice->paid_amount > 0)
                 <tr>
                    <td class="label">Amount Paid</td>
                    <td class="value success">-{{ $settings->safeFormatAmount($invoice->paid_amount) }}</td>
                 </tr>
                @endif
                <tr class="total-row">
                    <td class="label"><strong>Total {{ $invoice->status == 'paid' ? 'Paid' : 'Amount Due' }}</strong></td>
                    <td class="value"><strong>{{ $settings->safeFormatAmount($invoice->balance) }}</strong></td>
                 </tr>
             </table>
            
            <!-- Coverage Information for Bulk Invoices -->
            @if($invoice->is_bulk_payment && $invoice->isPaid() && !empty($invoice->covers_periods))
            <div class="coverage-info">
                <strong>📦 Bulk Coverage Active</strong><br>
                <div style="margin-top: 5px;">
                    <strong>Covered Periods:</strong>
                    @foreach($invoice->covers_periods as $period)
                        <span style="display: inline-block; background: white; padding: 2px 6px; border-radius: 4px; margin: 2px;">
                            {{ \Carbon\Carbon::parse($period . '-01')->format('M Y') }}
                        </span>
                    @endforeach
                    <div style="margin-top: 5px;">Total months covered: {{ count($invoice->covers_periods) }}</div>
                </div>
            </div>
            @endif
            
            <!-- Covered by Bulk Information -->
            @if($isCovered && $invoice->status != 'paid' && isset($coveringBulk))
            <div class="coverage-info" style="background: #d1fae5;">
                <strong>✅ Covered by Bulk Payment</strong><br>
                This period is covered by a bulk payment made on 
                {{ isset($coveringBulk['payment_date']) ? \Carbon\Carbon::parse($coveringBulk['payment_date'])->format('F d, Y') : 'N/A' }}.
                No payment is required for this period.
                <div style="margin-top: 5px;">
                    <span style="font-size: 9px;">Bulk Invoice: {{ $coveringBulk['invoice_number'] ?? 'INV-'.str_pad($coveringBulk['invoice_id'], 6, '0', STR_PAD_LEFT) }}</span>
                </div>
            </div>
            @endif
            
            <!-- Child Invoices for Bulk Payments -->
            @if($invoice->is_bulk_payment && $invoice->childInvoices && $invoice->childInvoices->count() > 0)
            <div class="coverage-info" style="background: #f3f4f6;">
                <strong>📄 Consolidated Invoices</strong>
                <div style="margin-top: 5px;">
                    @foreach($invoice->childInvoices as $child)
                        <span style="display: inline-block; background: white; padding: 2px 6px; border-radius: 4px; margin: 2px; font-size: 9px;">
                            {{ \Carbon\Carbon::parse($child->period . '-01')->format('M Y') }}
                        </span>
                    @endforeach
                </div>
                <div style="margin-top: 5px; font-size: 9px;">
                    Total: {{ $invoice->childInvoices->count() }} invoice(s) consolidated
                </div>
            </div>
            @endif
            
            <!-- Payment Instructions for Pending/Overdue -->
            @if(in_array($invoice->status, ['pending', 'overdue']) && !$isCovered)
            <div class="payment-instructions">
                <strong>💰 Payment Instructions:</strong><br>
                @if($settings->payment_mobile_number)
                    Mobile Money: {{ $settings->payment_mobile_number }} ({{ strtoupper($settings->payment_network ?? 'All Networks') }})<br>
                    Account Name: {{ $settings->payment_account_name ?? 'Property Management' }}<br>
                @endif
                @if($settings->bank_name && $settings->bank_account_number)
                    Bank Transfer: {{ $settings->bank_name }} - {{ $settings->bank_account_number }}<br>
                    Account Name: {{ $settings->bank_account_name ?? 'Property Management' }}<br>
                @endif
                <span style="font-size: 9px;">Reference: {{ $invoice->invoice_number ?? 'INV-'.str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</span>
            </div>
            @endif
            
            <!-- Overdue Notice -->
            @if($invoice->status == 'overdue')
            <div class="payment-instructions" style="background: #fee2e2; border-left-color: #dc2626;">
                <strong>⚠️ OVERDUE NOTICE:</strong> This invoice is {{ \Carbon\Carbon::parse($invoice->due_date)->diffInDays(now()) }} days overdue.
                @if($invoice->penalty_amount > 0)
                    Late penalty of {{ $settings->safeFormatAmount($invoice->penalty_amount) }} has been applied.
                @endif
            </div>
            @endif
        </div>
    </div>
    @endforeach
    
    <!-- Summary Footer -->
    <div class="footer">
        <p>Total Invoices: {{ $invoices->count() }} | Total Amount: {{ $settings->safeFormatAmount($invoices->sum('total_amount')) }}</p>
        <p>This is a computer-generated document. No signature is required.</p>
        <p>© {{ date('Y') }} {{ $settings->system_name ?? 'Property Management System' }}. All rights reserved.</p>
    </div>
</body>
</html>