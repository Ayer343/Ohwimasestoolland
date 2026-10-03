<!DOCTYPE html>
<html>
<head>
    <title>URGENT: Unpaid Invoice from Previous Year</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #ef4444; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background-color: #f9fafb; padding: 20px; border-radius: 0 0 5px 5px; }
        .urgent { background-color: #fee2e2; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #ef4444; }
        .button { display: inline-block; padding: 10px 20px; background-color: #ef4444; color: white; text-decoration: none; border-radius: 5px; }
        .footer { margin-top: 20px; font-size: 12px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>URGENT: Unpaid Invoice</h1>
            <p>{{ $settings->system_name ?? 'System' }}</p>
        </div>
        
        <div class="content">
            <p>Dear {{ $invoice->tenant->name ?? 'Tenant' }},</p>
            
            <div class="urgent">
                <p><strong>⚠️ URGENT: Payment Required</strong></p>
                <p>You have an unpaid invoice from <strong>{{ $invoice->month_name }}</strong> that requires your immediate attention.</p>
                <p><strong>Amount Due:</strong> {{ $settings->formatAmount($invoice->balance) }}</p>
            </div>
            
            <p>Please make your payment as soon as possible to avoid any late fees or penalties.</p>
            
            <p style="text-align: center;">
                <a href="{{ route('tenant.invoices.show', $invoice->id) }}" class="button">Pay Now</a>
            </p>
            
            <div class="footer">
                <p>If you have already made this payment, please contact us immediately.</p>
                <p>&copy; {{ date('Y') }} {{ $settings->system_name ?? 'System' }}. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>