<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice INV-{{ str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</title>
    <style>
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
            padding: 40px;
        }
        
        /* Invoice Container */
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }
        
        /* Header Section */
        .invoice-header {
            background: linear-gradient(135deg, #4F46E5 0%, #6366F1 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .invoice-number {
            font-size: 14px;
            opacity: 0.9;
        }
        
        /* Company Info */
        .company-info {
            padding: 20px 30px;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #4F46E5;
            margin-bottom: 5px;
        }
        
        .company-details {
            font-size: 11px;
            color: #6B7280;
        }
        
        /* Invoice Details */
        .invoice-details {
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .detail-section {
            flex: 1;
        }
        
        .detail-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6B7280;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 14px;
            font-weight: bold;
            color: #111827;
        }
        
        /* Property Info */
        .property-info {
            padding: 20px 30px;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #374151;
            margin-bottom: 12px;
            border-left: 3px solid #4F46E5;
            padding-left: 10px;
        }
        
        .info-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .info-item {
            flex: 1;
            min-width: 200px;
        }
        
        /* Amounts Table */
        .amounts-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .amounts-table td {
            padding: 12px;
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
            font-size: 16px;
        }
        
        .total-row td {
            border-top: 2px solid #e5e7eb;
            border-bottom: none;
        }
        
        /* Payment Instructions */
        .payment-instructions {
            background: #fef3c7;
            padding: 20px 30px;
            margin: 20px 30px;
            border-radius: 8px;
        }
        
        .instructions-title {
            font-weight: bold;
            color: #92400e;
            margin-bottom: 10px;
        }
        
        /* Coverage Info */
        .coverage-info {
            background: #e0e7ff;
            padding: 15px 30px;
            margin: 0 30px 20px 30px;
            border-radius: 8px;
        }
        
        /* Footer */
        .invoice-footer {
            padding: 20px 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            font-size: 9px;
            color: #9CA3AF;
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
            background: #fed7aa;
            color: #92400e;
        }
        
        .status-overdue {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Utilities */
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .mt-2 {
            margin-top: 5px;
        }
        
        .mb-2 {
            margin-bottom: 5px;
        }
        
        .font-bold {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="invoice-header">
            <div class="invoice-title">INVOICE</div>
            <div class="invoice-number">#INV-{{ str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</div>
        </div>
        
        <!-- Company Info -->
        <div class="company-info">
            <div class="company-name">{{ $settings->company_name ?? 'Property Management System' }}</div>
            <div class="company-details">
                @if($settings->company_address)
                    {{ $settings->company_address }}<br>
                @endif
                @if($settings->company_phone)
                    Phone: {{ $settings->company_phone }}<br>
                @endif
                @if($settings->company_email)
                    Email: {{ $settings->company_email }}
                @endif
            </div>
        </div>
        
        <!-- Invoice Details -->
        <div class="invoice-details">
            <div class="detail-section">
                <div class="detail-label">Invoice Date</div>
                <div class="detail-value">{{ $invoice->created_at->format('F d, Y') }}</div>
            </div>
            <div class="detail-section">
                <div class="detail-label">Due Date</div>
                <div class="detail-value">{{ $invoice->due_date->format('F d, Y') }}</div>
            </div>
            <div class="detail-section">
                <div class="detail-label">Status</div>
                <div class="detail-value">
                    @php
                        $statusClass = match($invoice->status) {
                            'paid' => 'status-paid',
                            'pending' => 'status-pending',
                            'overdue' => 'status-overdue',
                            default => ''
                        };
                    @endphp
                    <span class="status-badge {{ $statusClass }}">
                        {{ ucfirst($invoice->status) }}
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Property Info -->
        <div class="property-info">
            <div class="section-title">Property Details</div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="detail-label">Property Address</div>
                    <div class="detail-value">
                        {{ $invoice->property->house_number }} {{ $invoice->property->street_name }}
                        @if($invoice->property->block_number)
                            <br>Block: {{ $invoice->property->block_number }}
                        @endif
                        @if($invoice->property->zone)
                            <br>Zone: {{ $invoice->property->zone }}
                        @endif
                    </div>
                </div>
                <div class="info-item">
                    <div class="detail-label">Landlord</div>
                    <div class="detail-value">{{ $invoice->property->landlord->name }}</div>
                    <div class="company-details" style="margin-top: 5px;">
                        @if($invoice->property->landlord->email)
                            Email: {{ $invoice->property->landlord->email }}<br>
                        @endif
                        @if($invoice->property->landlord->phone)
                            Phone: {{ $invoice->property->landlord->phone }}
                        @endif
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Period Info -->
        <div style="padding: 0 30px;">
            <div class="section-title">Billing Period</div>
            <div style="padding: 10px 0;">
                @if($invoice->is_bulk_payment)
                    @if($invoice->bulk_start_month && $invoice->bulk_end_month)
                        <strong>{{ \Carbon\Carbon::parse($invoice->bulk_start_month . '-01')->format('F Y') }}</strong> 
                        to 
                        <strong>{{ \Carbon\Carbon::parse($invoice->bulk_end_month . '-01')->format('F Y') }}</strong>
                        <div style="margin-top: 5px; font-size: 11px; color: #6B7280;">
                            Bulk Payment ({{ $invoice->bulk_months }} months)
                        </div>
                    @else
                        <strong>Bulk Payment</strong>
                    @endif
                @else
                    <strong>{{ \Carbon\Carbon::parse($invoice->period . '-01')->format('F Y') }}</strong>
                @endif
            </div>
        </div>
        
        <!-- Amounts Table -->
        <div style="padding: 20px 30px;">
            <table class="amounts-table">
                <tr>
                    <td class="label">Monthly Dues</td>
                    <td class="value">{{ $settings->formatAmount($invoice->amount) }}</td>
                </tr>
                @if($invoice->penalty_amount > 0)
                <tr>
                    <td class="label">Late Payment Penalty</td>
                    <td class="value" style="color: #dc2626;">{{ $settings->formatAmount($invoice->penalty_amount) }}</td>
                </tr>
                @endif
                @if($invoice->discount_amount > 0)
                <tr>
                    <td class="label">Discount</td>
                    <td class="value" style="color: #059669;">-{{ $settings->formatAmount($invoice->discount_amount) }}</td>
                </tr>
                @endif
                @if($invoice->paid_amount > 0)
                <tr>
                    <td class="label">Amount Paid</td>
                    <td class="value" style="color: #059669;">-{{ $settings->formatAmount($invoice->paid_amount) }}</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td class="label"><strong>Total {{ $invoice->status == 'paid' ? 'Paid' : 'Due' }}</strong></td>
                    <td class="value">
                        <strong style="font-size: 18px; color: #4F46E5;">
                            {{ $settings->formatAmount($invoice->balance) }}
                        </strong>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Payment Instructions for Pending/Overdue -->
        @if(in_array($invoice->status, ['pending', 'overdue']))
        <div class="payment-instructions">
            <div class="instructions-title">💰 Payment Instructions</div>
            <div style="font-size: 11px;">
                <p>Please make payment using any of the following methods:</p>
                <div style="margin-top: 10px;">
                    <strong>Mobile Money:</strong><br>
                    {{ $settings->payment_network ?? 'All Networks' }}: {{ $settings->payment_mobile_number ?? 'N/A' }}<br>
                    Account Name: {{ $settings->payment_account_name ?? 'Property Management' }}
                </div>
                <div style="margin-top: 10px;">
                    <strong>Bank Transfer:</strong><br>
                    Bank: {{ $settings->bank_name ?? 'N/A' }}<br>
                    Account Number: {{ $settings->bank_account_number ?? 'N/A' }}<br>
                    Account Name: {{ $settings->bank_account_name ?? 'N/A' }}
                </div>
                <div style="margin-top: 10px; font-size: 10px; color: #92400e;">
                    <strong>Reference:</strong> Please use invoice number INV-{{ str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }} as payment reference.
                </div>
            </div>
        </div>
        @endif
        
        <!-- Coverage Info for Bulk Invoices -->
        @if($invoice->is_bulk_payment && $invoice->isPaid() && !empty($invoice->covers_periods))
        <div class="coverage-info">
            <div style="font-weight: bold; margin-bottom: 8px;">📦 Bulk Coverage Active</div>
            <div style="font-size: 11px;">
                This bulk payment covers the following periods. No invoices will be generated for these months:
                <div style="margin-top: 8px;">
                    @foreach($invoice->covers_periods as $period)
                        <span style="display: inline-block; background: white; padding: 3px 8px; border-radius: 4px; margin: 2px;">
                            {{ \Carbon\Carbon::parse($period . '-01')->format('M Y') }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
        
        <!-- Child Invoices for Bulk Payments -->
        @if($invoice->is_bulk_payment && $invoice->childInvoices && $invoice->childInvoices->count() > 0)
        <div style="padding: 0 30px 20px 30px;">
            <div class="section-title">Consolidated Invoices</div>
            <div style="margin-top: 10px;">
                <table style="width: 100%; font-size: 11px;">
                    <thead>
                        <tr style="background: #f9fafb;">
                            <th style="padding: 8px; text-align: left;">Invoice #</th>
                            <th style="padding: 8px; text-align: left;">Period</th>
                            <th style="padding: 8px; text-align: right;">Amount</th>
                            <th style="padding: 8px; text-align: left;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->childInvoices as $child)
                        <tr>
                            <td style="padding: 6px;">INV-{{ str_pad($child->id, 6, '0', STR_PAD_LEFT) }}</td>
                            <td style="padding: 6px;">{{ \Carbon\Carbon::parse($child->period . '-01')->format('M Y') }}</td>
                            <td style="padding: 6px; text-align: right;">{{ $settings->formatAmount($child->amount) }}</td>
                            <td style="padding: 6px;">Consolidated</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
        
        <!-- Footer -->
        <div class="invoice-footer">
            <p>This is a computer-generated invoice. No signature is required.</p>
            <p>For inquiries, please contact support at {{ $settings->support_email ?? 'support@example.com' }}</p>
            <p>© {{ date('Y') }} {{ $settings->company_name ?? 'Property Management System' }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>