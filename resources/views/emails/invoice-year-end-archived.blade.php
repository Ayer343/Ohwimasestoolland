<!DOCTYPE html>
<html>
<head>
    <title>Invoice Archived</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #10b981; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background-color: #f9fafb; padding: 20px; border-radius: 0 0 5px 5px; }
        .invoice-details { background-color: white; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #10b981; }
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
            
            <p>Your invoice from <strong>{{ $year }}</strong> has been archived.</p>
            
            <div class="invoice-details">
                <h3>Archived Invoice Details:</h3>
                <p><strong>Invoice Number:</strong> #{{ $invoice->invoice_number }}</p>
                <p><strong>Period:</strong> {{ $invoice->month_name }}</p>
                <p><strong>Payment Date:</strong> {{ $invoice->payment_date?->format('F j, Y') }}</p>
                <p><strong>Amount Paid:</strong> {{ $settings->formatAmount($invoice->paid_amount) }}</p>
            </div>
            
            <p>You can still view this invoice in your archived invoices section.</p>
            
            <div class="footer">
                <p>&copy; {{ date('Y') }} {{ $settings->system_name ?? 'System' }}. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>