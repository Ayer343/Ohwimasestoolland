<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>New Invoice</title></head>
<body style="font-family: Arial, sans-serif; color: #333; max-width: 640px; margin: 0 auto; padding: 24px;">
    <h2 style="color: #111;">New Invoice Generated</h2>
    <p>Hello {{ $tenant->name }},</p>
    <p>Your invoice <strong>{{ $invoice->invoice_number }}</strong> for the period
       <strong>{{ \Carbon\Carbon::parse($invoice->period . '-01')->format('F Y') }}</strong>
       has been generated.</p>

    <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
        <tr><td style="padding: 6px 0;">Amount</td><td style="text-align: right;"><strong>{{ $settings->formatAmount($invoice->total_amount) }}</strong></td></tr>
        <tr><td style="padding: 6px 0;">Due Date</td><td style="text-align: right;">{{ optional($invoice->due_date)->format('F j, Y') }}</td></tr>
        <tr><td style="padding: 6px 0;">Status</td><td style="text-align: right;">{{ ucfirst($invoice->status) }}</td></tr>
    </table>

    <p>Thank you,<br>{{ $settings->system_name }}</p>
</body>
</html>