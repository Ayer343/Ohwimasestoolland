<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Selected Invoices - {{ now()->format('F Y') }}</title>
    <style>
        /* Same base styles as above with slight modifications */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #4F46E5;
            padding-bottom: 15px;
        }
        
        .company-name {
            font-size: 22px;
            font-weight: bold;
            color: #4F46E5;
        }
        
        .report-title {
            font-size: 16px;
            font-weight: bold;
            color: #666;
            margin-top: 5px;
        }
        
        .report-date {
            font-size: 10px;
            color: #999;
            margin-top: 3px;
        }
        
        .selection-info {
            background: #f3f4f6;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: inline-block;
        }
        
        .invoice-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 20px;
            page-break-inside: avoid;
            overflow: hidden;
        }
        
        .invoice-header {
            background: #f9fafb;
            padding: 12px 15px;
            border-bottom: 2px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .invoice-number {
            font-size: 14px;
            font-weight: bold;
            color: #4F46E5;
        }
        
        .invoice-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .invoice-body {
            padding: 15px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 12px;
            border-bottom: 1px dashed #e5e7eb;
            padding-bottom: 8px;
        }
        
        .info-label {
            width: 120px;
            font-weight: bold;
            color: #6B7280;
        }
        
        .info-value {
            flex: 1;
            color: #111827;
        }
        
        .amounts-grid {
            display: flex;
            gap: 20px;
            margin: 15px 0;
            padding: 12px;
            background: #f9fafb;
            border-radius: 6px;
        }
        
        .amount-item {
            flex: 1;
            text-align: center;
        }
        
        .amount-label {
            font-size: 10px;
            color: #6B7280;
            margin-bottom: 5px;
        }
        
        .amount-value {
            font-size: 16px;
            font-weight: bold;
        }
        
        .coverage-info {
            background: #e0e7ff;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 11px;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #9CA3AF;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .font-bold {
            font-weight: bold;
        }
        
        .mt-2 {
            margin-top: 5px;
        }
        
        .mb-2 {
            margin-bottom: 5px;
        }
        
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
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="company-name">{{ $settings->company_name ?? 'Property Management System' }}</div>
        <div class="report-title">Selected Invoices Report</div>
        <div class="report-date">Generated on: {{ now()->format('F d, Y H:i:s') }}</div>
    </div>

    <!-- Selection Summary -->
    <div class="selection-info">
        <strong>Selected Invoices:</strong> {{ $invoices->count() }} invoices | 
        <strong>Total Amount:</strong> {{ $settings->formatAmount($invoices->sum('total_amount')) }}
    </div>

    <!-- Individual Invoice Cards -->
    @foreach($invoices as $invoice)
    <div class="invoice-card">
        <div class="invoice-header">
            <div>
                <div class="invoice-number">INV-{{ str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</div>
                <div style="font-size: 10px; color: #6B7280; margin-top: 3px;">
                    {{ $invoice->period ? \Carbon\Carbon::parse($invoice->period . '-01')->format('F Y') : 'Bulk Payment' }}
                </div>
            </div>
            <div>
                @php
                    $statusClass = match($invoice->status) {
                        'paid' => 'status-paid',
                        'pending' => 'status-pending',
                        'overdue' => 'status-overdue',
                        'consolidated' => 'status-consolidated',
                        default => ''
                    };
                @endphp
                <span class="invoice-status {{ $statusClass }}">
                    {{ $invoice->status == 'consolidated' ? 'In Bulk' : ucfirst($invoice->status) }}
                </span>
            </div>
        </div>
        
        <div class="invoice-body">
            <div class="info-row">
                <div class="info-label">Property:</div>
                <div class="info-value">
                    {{ $invoice->property->house_number }} {{ $invoice->property->street_name }}
                    @if($invoice->property->block_number)
                        <br><span style="font-size: 10px;">Block: {{ $invoice->property->block_number }}</span>
                    @endif
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Landlord:</div>
                <div class="info-value">{{ $invoice->property->landlord->name }}</div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Due Date:</div>
                <div class="info-value">{{ $invoice->due_date->format('F d, Y') }}</div>
            </div>
            
            @if($invoice->description)
            <div class="info-row">
                <div class="info-label">Description:</div>
                <div class="info-value">{{ $invoice->description }}</div>
            </div>
            @endif
            
            <div class="amounts-grid">
                <div class="amount-item">
                    <div class="amount-label">Base Amount</div>
                    <div class="amount-value">{{ $settings->formatAmount($invoice->amount) }}</div>
                </div>
                @if($invoice->penalty_amount > 0)
                <div class="amount-item">
                    <div class="amount-label">Penalty</div>
                    <div class="amount-value" style="color: #dc2626;">{{ $settings->formatAmount($invoice->penalty_amount) }}</div>
                </div>
                @endif
                @if($invoice->discount_amount > 0)
                <div class="amount-item">
                    <div class="amount-label">Discount</div>
                    <div class="amount-value" style="color: #059669;">-{{ $settings->formatAmount($invoice->discount_amount) }}</div>
                </div>
                @endif
                <div class="amount-item">
                    <div class="amount-label">Total Amount</div>
                    <div class="amount-value" style="color: #4F46E5;">{{ $settings->formatAmount($invoice->total_amount) }}</div>
                </div>
            </div>
            
            @if($invoice->payment_method)
            <div class="info-row">
                <div class="info-label">Payment Method:</div>
                <div class="info-value">{{ ucfirst(str_replace('_', ' ', $invoice->payment_method)) }}</div>
            </div>
            @endif
            
            @if($invoice->payment_reference)
            <div class="info-row">
                <div class="info-label">Payment Reference:</div>
                <div class="info-value">{{ $invoice->payment_reference }}</div>
            </div>
            @endif
            
            @if($invoice->payment_date)
            <div class="info-row">
                <div class="info-label">Payment Date:</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($invoice->payment_date)->format('F d, Y') }}</div>
            </div>
            @endif
            
            <!-- Coverage Information for Bulk Invoices -->
            @if($invoice->is_bulk_payment && $invoice->isPaid() && !empty($invoice->covers_periods))
            <div class="coverage-info">
                <strong>📦 Bulk Coverage Active</strong>
                <div style="margin-top: 5px;">
                    <div>Covered Periods: 
                        @foreach($invoice->covers_periods as $period)
                            <span style="display: inline-block; background: white; padding: 2px 6px; border-radius: 4px; margin: 2px; font-size: 9px;">
                                {{ \Carbon\Carbon::parse($period . '-01')->format('M Y') }}
                            </span>
                        @endforeach
                    </div>
                    <div style="margin-top: 5px;">Total months covered: {{ count($invoice->covers_periods) }}</div>
                </div>
            </div>
            @endif
            
            <!-- Child Invoices for Bulk Payments -->
            @if($invoice->is_bulk_payment && $invoice->childInvoices && $invoice->childInvoices->count() > 0)
            <div class="coverage-info" style="background: #f3f4f6; margin-top: 10px;">
                <strong>📄 Consolidated Invoices</strong>
                <div style="margin-top: 5px;">
                    @foreach($invoice->childInvoices as $child)
                        <span style="display: inline-block; background: white; padding: 2px 6px; border-radius: 4px; margin: 2px; font-size: 9px;">
                            INV-{{ str_pad($child->id, 6, '0', STR_PAD_LEFT) }} - 
                            {{ \Carbon\Carbon::parse($child->period . '-01')->format('M Y') }}
                        </span>
                    @endforeach
                </div>
            </div>
            @endif
            
            <!-- Payment Instructions for Pending Invoices -->
            @if(in_array($invoice->status, ['pending', 'overdue']))
            <div style="margin-top: 15px; padding: 10px; background: #fef3c7; border-radius: 6px; font-size: 10px;">
                <strong>💰 Payment Instructions:</strong><br>
                Please make payment to: {{ $settings->payment_account_name ?? 'Property Management' }}<br>
                Mobile Money: {{ $settings->payment_mobile_number ?? 'N/A' }} ({{ $settings->payment_network ?? 'All Networks' }})<br>
                Reference: INV-{{ str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}
            </div>
            @endif
        </div>
    </div>
    @endforeach
    
    <!-- Summary Footer -->
    <div class="footer">
        <p>Total Invoices: {{ $invoices->count() }} | Total Amount: {{ $settings->formatAmount($invoices->sum('total_amount')) }}</p>
        <p>This is a computer-generated document. No signature is required.</p>
        <p>© {{ date('Y') }} {{ $settings->company_name ?? 'Property Management System' }}. All rights reserved.</p>
    </div>
</body>
</html>