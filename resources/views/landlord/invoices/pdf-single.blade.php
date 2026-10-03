<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number ?? 'INV-'.str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</title>
    
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
            margin: 0;
            padding: 20px;
            color: #333;
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
        
        /* Header */
        .invoice-header {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
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
            text-align: center;
        }
        
        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #10B981;
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
            background: #f9fafb;
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
            border-bottom: 1px solid #e5e7eb;
        }
        
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #374151;
            margin-bottom: 12px;
            border-left: 3px solid #10B981;
            padding-left: 10px;
        }
        
        .info-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
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
        }
        
        .total-row td {
            border-top: 2px solid #e5e7eb;
            font-size: 16px;
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
            background: #d1fae5;
            padding: 15px 30px;
            margin: 0 30px 20px 30px;
            border-radius: 8px;
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
        
        .status-consolidated {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        /* Footer */
        .invoice-footer {
            padding: 20px 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            font-size: 9px;
            color: #9CA3AF;
            background: #f9fafb;
        }
        
        .thank-you {
            text-align: center;
            padding: 20px;
            background: #f0fdf4;
            margin: 20px 30px;
            border-radius: 8px;
            font-style: italic;
            color: #166534;
        }
        
        .warning {
            color: #dc2626;
        }
        
        .success {
            color: #10B981;
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
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Header -->
        <div class="invoice-header">
            <div class="invoice-title">PROPERTY INVOICE</div>
            <div class="invoice-number">{{ $invoice->invoice_number ?? 'INV-'.str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</div>
        </div>
        
        <!-- Company Info -->
        <div class="company-info">
            <div class="company-name">{{ $settings->system_name ?? 'Property Management System' }}</div>
            <div class="company-details">
                @if($settings->system_email) Email: {{ $settings->system_email }} @endif
                @if($settings->system_phone) | Phone: {{ $settings->system_phone }} @endif
                @if($settings->system_address) | Address: {{ $settings->system_address }} @endif
                @if($settings->currency_code) | Currency: {{ $settings->currency_code }} @endif
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
                            'consolidated' => 'status-consolidated',
                            default => ''
                        };
                    @endphp
                    <span class="status-badge {{ $statusClass }}">
                        {{ $invoice->status == 'consolidated' ? 'In Bulk' : ucfirst($invoice->status) }}
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
                        @if($invoice->property)
                            <strong>{{ $invoice->property->property_name ?? '' }}</strong><br>
                            {{ $invoice->property->house_number ?? '#' }} {{ $invoice->property->street_name }}
                            @if($invoice->property->block_number)
                                <br>Block: {{ $invoice->property->block_number }}
                            @endif
                            @if($invoice->property->zone)
                                <br>Zone: {{ $invoice->property->zone }}
                            @endif
                        @else
                            Property not found
                        @endif
                    </div>
                </div>
                <div class="info-item">
                    <div class="detail-label">Landlord Information</div>
                    <div class="detail-value">
                        {{ auth()->user()->name }}<br>
                        @if(auth()->user()->email)
                            Email: {{ auth()->user()->email }}<br>
                        @endif
                        @if(auth()->user()->phone)
                            Phone: {{ auth()->user()->phone }}
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
                    @if($invoice->bulk_coverage_start && $invoice->bulk_coverage_end)
                        <strong>{{ \Carbon\Carbon::parse($invoice->bulk_coverage_start . '-01')->format('F Y') }}</strong> 
                        to 
                        <strong>{{ \Carbon\Carbon::parse($invoice->bulk_coverage_end . '-01')->format('F Y') }}</strong>
                        <div style="margin-top: 5px; font-size: 11px; color: #6B7280;">
                            Bulk Payment ({{ $invoice->bulk_months ?? count($invoice->covers_periods ?? []) }} months prepaid)
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
                    <td class="value">
                        <strong style="font-size: 18px; color: #10B981;">
                            {{ $settings->safeFormatAmount($invoice->balance) }}
                        </strong>
                    </td>
                 </tr>
             </table>
        </div>
        
        <!-- Payment Instructions for Pending/Overdue -->
        @if(in_array($invoice->status, ['pending', 'overdue']))
        <div class="payment-instructions">
            <div class="instructions-title">📱 How to Pay</div>
            <div style="font-size: 11px;">
                @if($settings->payment_mobile_number)
                    <div style="margin-bottom: 8px;">
                        <strong>Mobile Money:</strong><br>
                        Network: {{ strtoupper($settings->payment_network ?? 'All Networks') }}<br>
                        Number: {{ $settings->payment_mobile_number }}<br>
                        Account Name: {{ $settings->payment_account_name ?? 'Property Management' }}
                    </div>
                @endif
                @if($settings->bank_name && $settings->bank_account_number)
                    <div style="margin-bottom: 8px;">
                        <strong>Bank Transfer:</strong><br>
                        Bank: {{ $settings->bank_name }}<br>
                        Account: {{ $settings->bank_account_number }}<br>
                        Account Name: {{ $settings->bank_account_name ?? 'Property Management' }}
                    </div>
                @endif
                <div style="margin-top: 8px; padding: 8px; background: #fff; border-radius: 4px;">
                    <strong>📌 Payment Reference:</strong><br>
                    <span style="font-family: monospace; font-size: 12px;">{{ $invoice->invoice_number ?? 'INV-'.str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</span>
                </div>
            </div>
        </div>
        @endif
        
        <!-- Coverage Info for Bulk Invoices -->
        @if($invoice->is_bulk_payment && $invoice->isPaid() && !empty($invoice->covers_periods))
        <div class="coverage-info">
            <div style="font-weight: bold; margin-bottom: 8px;">✅ Bulk Payment Coverage Active</div>
            <div style="font-size: 11px;">
                Your bulk payment covers the following months. No further payment is required for these periods:
                <div style="margin-top: 8px;">
                    @foreach($invoice->covers_periods as $period)
                        <span style="display: inline-block; background: white; padding: 3px 8px; border-radius: 4px; margin: 2px;">
                            {{ \Carbon\Carbon::parse($period . '-01')->format('F Y') }}
                        </span>
                    @endforeach
                </div>
                <div style="margin-top: 8px; font-size: 10px;">
                    Total months covered: {{ count($invoice->covers_periods) }}
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
                         </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->childInvoices as $child)
                         <tr>
                            <td style="padding: 6px;">{{ $child->invoice_number ?? 'INV-'.str_pad($child->id, 6, '0', STR_PAD_LEFT) }}</td>
                            <td style="padding: 6px;">{{ \Carbon\Carbon::parse($child->period . '-01')->format('M Y') }}</td>
                            <td style="padding: 6px; text-align: right;">{{ $settings->safeFormatAmount($child->amount) }}</td>
                         </tr>
                        @endforeach
                    </tbody>
                 </table>
            </div>
        </div>
        @endif
        
        <!-- Thank You Note for Paid Invoices -->
        @if($invoice->status == 'paid')
        <div class="thank-you">
            <p>✓ Thank you for your payment!</p>
            <p style="font-size: 10px; margin-top: 5px;">
                Payment received on {{ \Carbon\Carbon::parse($invoice->payment_date)->format('F d, Y') }}
                @if($invoice->payment_method)
                    via {{ ucfirst(str_replace('_', ' ', $invoice->payment_method)) }}
                @endif
            </p>
            @if($invoice->payment_reference)
                <p style="font-size: 9px;">Reference: {{ $invoice->payment_reference }}</p>
            @endif
        </div>
        @endif
        
        <!-- Payment Reminder for Overdue -->
        @if($invoice->status == 'overdue')
        <div class="payment-instructions" style="background: #fee2e2;">
            <div class="instructions-title" style="color: #991b1b;">⚠️ Payment Overdue</div>
            <div style="font-size: 11px;">
                This invoice is overdue by {{ \Carbon\Carbon::parse($invoice->due_date)->diffInDays(now()) }} days. 
                Please make payment immediately to avoid additional penalties.
                @if($invoice->penalty_amount > 0)
                    <br><strong>Late penalty of {{ $settings->safeFormatAmount($invoice->penalty_amount) }} has been applied.</strong>
                @endif
            </div>
        </div>
        @endif
        
        <!-- Footer -->
        <div class="invoice-footer">
            <p>For inquiries, please contact: {{ $settings->system_email ?? 'support@example.com' }} | {{ $settings->system_phone ?? 'N/A' }}</p>
            <p>This is a computer-generated invoice. No signature is required.</p>
            <p>© {{ date('Y') }} {{ $settings->system_name ?? 'Property Management System' }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>