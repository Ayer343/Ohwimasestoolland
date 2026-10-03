<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $invoice->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            padding: 20px;
        }
        
        .invoice-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
        }
        
        /* Header Styles */
        .header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #4f46e5;
        }
        
        .company-info {
            float: left;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #4f46e5;
            margin-bottom: 5px;
        }
        
        .company-details {
            font-size: 10px;
            color: #666;
        }
        
        .invoice-title {
            float: right;
            text-align: right;
        }
        
        .invoice-title h1 {
            font-size: 28px;
            color: #4f46e5;
            margin-bottom: 5px;
        }
        
        .invoice-number {
            font-size: 14px;
            color: #666;
        }
        
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
        
        /* Invoice Details */
        .invoice-details {
            margin-bottom: 30px;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .detail-label {
            width: 120px;
            font-weight: bold;
            color: #4f46e5;
        }
        
        .detail-value {
            flex: 1;
            color: #333;
        }
        
        /* Tenant & Property Info */
        .info-section {
            margin-bottom: 30px;
        }
        
        .info-box {
            width: 48%;
            float: left;
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
            margin-right: 4%;
        }
        
        .info-box:last-child {
            margin-right: 0;
        }
        
        .info-title {
            font-size: 14px;
            font-weight: bold;
            color: #4f46e5;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .items-table th {
            background: #f3f4f6;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            color: #4f46e5;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .items-table .text-right {
            text-align: right;
        }
        
        /* Totals */
        .totals {
            width: 300px;
            float: right;
            margin-bottom: 30px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .total-row.grand-total {
            font-weight: bold;
            font-size: 14px;
            color: #4f46e5;
            border-top: 2px solid #4f46e5;
            border-bottom: none;
            padding-top: 10px;
            margin-top: 5px;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-overdue {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-cancelled {
            background: #f3f4f6;
            color: #374151;
        }
        
        /* Payment Instructions */
        .payment-instructions {
            margin-top: 30px;
            padding: 15px;
            background: #fef3c7;
            border-radius: 8px;
            clear: both;
        }
        
        .payment-instructions h3 {
            font-size: 14px;
            font-weight: bold;
            color: #92400e;
            margin-bottom: 10px;
        }
        
        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
        }
        
        @media print {
            body {
                padding: 0;
            }
            .status-badge {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="header clearfix">
            <div class="company-info">
                <div class="company-name">{{ $companyInfo['name'] }}</div>
                <div class="company-details">
                    @if($companyInfo['email'])<div>Email: {{ $companyInfo['email'] }}</div>@endif
                    @if($companyInfo['phone'])<div>Phone: {{ $companyInfo['phone'] }}</div>@endif
                    @if($companyInfo['address'])<div>Address: {{ $companyInfo['address'] }}</div>@endif
                </div>
            </div>
            <div class="invoice-title">
                <h1>INVOICE</h1>
                <div class="invoice-number">#{{ $invoice->invoice_number }}</div>
            </div>
        </div>
        
        <!-- Invoice Details -->
        <div class="invoice-details">
            <div class="detail-row">
                <div class="detail-label">Invoice Date:</div>
                <div class="detail-value">{{ $invoice->created_at->format('F j, Y') }}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Due Date:</div>
                <div class="detail-value">{{ $invoice->due_date->format('F j, Y') }}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Period:</div>
                <div class="detail-value">{{ $invoice->month_name }}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Status:</div>
                <div class="detail-value">
                    <span class="status-badge status-{{ $invoice->status }}">
                        {{ ucfirst($invoice->status) }}
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Tenant & Property Info -->
        <div class="info-section clearfix">
            <div class="info-box">
                <div class="info-title">Tenant Information</div>
                <div><strong>Name:</strong> {{ $invoice->tenant->name }}</div>
                <div><strong>Email:</strong> {{ $invoice->tenant->email }}</div>
                @if($invoice->tenant->phone)
                <div><strong>Phone:</strong> {{ $invoice->tenant->phone }}</div>
                @endif
            </div>
            <div class="info-box">
                <div class="info-title">Property Information</div>
                <div><strong>Property:</strong> {{ $invoice->propertyUnit->property->property_name ?? 'N/A' }}</div>
                <div><strong>Unit Number:</strong> {{ $invoice->propertyUnit->unit_number ?? 'N/A' }}</div>
                @if($invoice->propertyUnit->property->zone)
                <div><strong>Zone:</strong> {{ $invoice->propertyUnit->property->zone }}</div>
                @endif
            </div>
        </div>
        
        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Amount ({{ $settings->currency_symbol }})</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Community Development Dues - {{ $invoice->month_name }}</td>
                    <td class="text-right">{{ number_format($invoice->community_dues, 2) }}</td>
                </tr>
                @if($invoice->additional_charges > 0)
                <tr>
                    <td>Additional Charges</td>
                    <td class="text-right">{{ number_format($invoice->additional_charges, 2) }}</td>
                </tr>
                @endif
                @if($invoice->penalty_amount > 0)
                <tr>
                    <td>Late Payment Penalty</td>
                    <td class="text-right">{{ number_format($invoice->penalty_amount, 2) }}</td>
                </tr>
                @endif
            </tbody>
        </table>
        
        <!-- Totals -->
        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>{{ $settings->formatAmount($invoice->total_amount - ($invoice->penalty_amount ?? 0)) }}</span>
            </div>
            @if($invoice->penalty_amount > 0)
            <div class="total-row">
                <span>Penalty:</span>
                <span>{{ $settings->formatAmount($invoice->penalty_amount) }}</span>
            </div>
            @endif
            <div class="total-row grand-total">
                <span>Total Amount:</span>
                <span>{{ $settings->formatAmount($invoice->total_amount) }}</span>
            </div>
            @if($invoice->paid_amount > 0)
            <div class="total-row">
                <span>Amount Paid:</span>
                <span>{{ $settings->formatAmount($invoice->paid_amount) }}</span>
            </div>
            <div class="total-row">
                <span>Balance Due:</span>
                <span style="color: {{ $invoice->balance > 0 ? '#dc2626' : '#10b981' }};">
                    {{ $settings->formatAmount($invoice->balance) }}
                </span>
            </div>
            @endif
        </div>
        
        <!-- Payment Information -->
        <div class="clearfix"></div>
        
        @if($invoice->status === 'paid')
        <div class="payment-instructions" style="background: #d1fae5;">
            <h3 style="color: #065f46;">✓ Payment Confirmation</h3>
            <p>This invoice has been fully paid on {{ $invoice->payment_date ? $invoice->payment_date->format('F j, Y') : 'N/A' }}.</p>
            <p>Payment Method: {{ ucfirst(str_replace('_', ' ', $invoice->payment_method ?? 'N/A')) }}</p>
            @if($invoice->payment_reference)
            <p>Reference: {{ $invoice->payment_reference }}</p>
            @endif
        </div>
        @else
        <!-- Payment Instructions -->
        <div class="payment-instructions">
            <h3>Payment Instructions</h3>
            @if(isset($paymentInstructions['general']))
            <p>{{ $paymentInstructions['general'] }}</p>
            @endif
            
            @if(isset($paymentInstructions['mobile_money']))
            <p><strong>Mobile Money:</strong><br>
            Provider: {{ ucfirst($paymentInstructions['mobile_money']['provider']) }}<br>
            Number: {{ $paymentInstructions['mobile_money']['number'] }}<br>
            Name: {{ $paymentInstructions['mobile_money']['name'] }}</p>
            @endif
            
            @if(isset($paymentInstructions['bank']))
            <p><strong>Bank Transfer:</strong><br>
            Bank: {{ $paymentInstructions['bank']['bank_name'] }}<br>
            Account: {{ $paymentInstructions['bank']['account_number'] }}<br>
            Name: {{ $paymentInstructions['bank']['account_name'] }}</p>
            @endif
            
            @if($invoice->within_grace_period)
            <p style="color: #eab308; margin-top: 10px;">
                <strong>⚠️ Note:</strong> You are currently within the grace period. 
                Payment can be made without penalty until {{ $invoice->grace_period_end->format('F j, Y') }}.
            </p>
            @endif
            
            @if($invoice->isOverdue() && !$invoice->isPaid())
            <p style="color: #dc2626; margin-top: 10px;">
                <strong>⚠️ Overdue Notice:</strong> This invoice is overdue. Please make payment immediately to avoid additional penalties.
            </p>
            @endif
        </div>
        @endif
        
        <!-- Footer -->
        <div class="footer">
            <p>This is a computer-generated document and requires no signature.</p>
            <p>For any questions regarding this invoice, please contact {{ $companyInfo['email'] }} or call {{ $companyInfo['phone'] }}.</p>
            <p>Thank you for your prompt payment.</p>
        </div>
    </div>
</body>
</html>