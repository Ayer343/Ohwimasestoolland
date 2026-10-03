<!DOCTYPE html>
<html>
<head>
    <title>Invoice Archived After Payment</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #6b7280; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background-color: #f9fafb; padding: 20px; border-radius: 0 0 5px 5px; }
        .footer { margin-top: 20px; font-size: 12px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Invoice Archived</h1>
            <p>{{ $settings->system_name ?? 'System' }}</p>
        </div>
        
        <div class="content">
            <p>Dear {{ $invoice->tenant->name ?? 'Tenant' }},</p>
            
            <p>Your invoice #{{ $invoice->invoice_number }} for period {{ $invoice->month_name }} has been archived after payment.</p>
            
            <p><strong>Payment Details:</strong></p>
            <ul>
                <li>Payment Date: {{ $invoice->payment_date?->format('F j, Y') }}</li>
                <li>Amount Paid: {{ $settings->formatAmount($invoice->paid_amount) }}</li>
                <li>Payment Method: {{ ucfirst(str_replace('_', ' ', $invoice->payment_method)) }}</li>
            </ul>
            
            <p>You can view this invoice in your archived invoices section at any time.</p>
            
            <div class="footer">
                <p>Thank you for your payment.</p>
                <p>&copy; {{ date('Y') }} {{ $settings->system_name ?? 'System' }}. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>