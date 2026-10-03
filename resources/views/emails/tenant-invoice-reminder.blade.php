<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Payment Reminder</title></head>
<body style="font-family: Arial, sans-serif; color: #333; max-width: 640px; margin: 0 auto; padding: 24px;">
    <h2 style="color: #b45309;">Payment Reminder</h2>
    <p>Hello {{ $tenant->name }},</p>
    <p>This is a reminder that invoice <strong>{{ $invoice->invoice_number }}</strong>
       is due on <strong>{{ optional($invoice->due_date)->format('F j, Y') }}</strong>.</p>

    <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
        <tr><td style="padding: 6px 0;">Amount Due</td><td style="text-align: right;"><strong>{{ $settings->formatAmount($invoice->balance ?? $invoice->total_amount) }}</strong></td></tr>
    </table>

    <p>Please make payment to avoid penalties.</p>

    <p>Thank you,<br>{{ $settings->system_name }}</p>
</body>
</html>