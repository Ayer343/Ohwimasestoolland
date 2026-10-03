<!DOCTYPE html>
<html>
<head>
    <title>Unpaid Invoice from {{ $year }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #f59e0b; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background-color: #f9fafb; padding: 20px; border-radius: 0 0 5px 5px; }
        .invoice-details { background-color: white; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #f59e0b; }
        .button { display: inline-block; padding: 10px 20px; background-color: #f59e0b; color: white; text-decoration: none; border-radius: 5px; }
        .footer { margin-top: 20px; font-size: 12px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Unpaid Invoice Notification</h1>
            <p>{{ $settings->system_name ?? 'System' }}</p>
        </div>
        
        <div class="content">
            <p>Dear {{ $invoice->tenant->name ?? 'Tenant' }},</p>
            
            <p>This is a reminder that you have an unpaid invoice from <strong>{{ $year }}</strong>.</p>
            
            <div class="invoice-details">
                <h3>Invoice Details:</h3>
                <p><strong>Invoice Number:</strong> #{{ $invoice->invoice_number }}</p>
                <p><strong>Period:</strong> {{ $invoice->month_name }}</p>
                <p><strong>Due Date:</strong> {{ $invoice->due_date->format('F j, Y') }}</p>
                <p><strong>Amount Due:</strong> {{ $settings->formatAmount($invoice->balance) }}</p>
                <p><strong>Status:</strong> {{ ucfirst($invoice->status) }}</p>
            </div>
            
            <p>Please make your payment as soon as possible to avoid any further penalties.</p>
            
            <p style="text-align: center;">
                <a href="{{ route('tenant.invoices.show', $invoice->id) }}" class="button">View Invoice</a>
            </p>
            
            <div class="footer">
                <p>If you have already made this payment, please disregard this notice.</p>
                <p>&copy; {{ date('Y') }} {{ $settings->system_name ?? 'System' }}. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>