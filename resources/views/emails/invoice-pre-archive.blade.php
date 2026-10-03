<!DOCTYPE html>
<html>
<head>
    <title>Upcoming Invoice Archiving</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #3b82f6; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
        .content { background-color: #f9fafb; padding: 20px; border-radius: 0 0 5px 5px; }
        .warning { background-color: #fef3c7; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #f59e0b; }
        .footer { margin-top: 20px; font-size: 12px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Invoice Archive Notification</h1>
            <p>{{ $settings->system_name ?? 'System' }}</p>
        </div>
        
        <div class="content">
            <p>Dear {{ $invoice->tenant->name ?? 'Tenant' }},</p>
            
            <div class="warning">
                <p><strong>⚠️ Important Notice</strong></p>
                <p>Your invoice #{{ $invoice->invoice_number }} for period {{ $invoice->month_name }} will be archived on <strong>{{ $archive_date }}</strong>.</p>
                <p>This invoice has been paid and will be moved to your archived records.</p>
            </div>
            
            <p>If you need a copy of this invoice, please download it before the archive date.</p>
            
            <div class="footer">
                <p>&copy; {{ date('Y') }} {{ $settings->system_name ?? 'System' }}. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>