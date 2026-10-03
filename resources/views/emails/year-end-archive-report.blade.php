{{-- resources/views/emails/year-end-archive-report.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>Year-End Archive Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4f46e5;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 20px;
            border-radius: 0 0 5px 5px;
        }
        .stats {
            margin: 20px 0;
            padding: 15px;
            background-color: white;
            border-radius: 5px;
            border-left: 4px solid #4f46e5;
        }
        .success {
            color: #10b981;
        }
        .warning {
            color: #f59e0b;
        }
        .error {
            color: #ef4444;
        }
        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Year-End Archive Report</h1>
            <p>{{ $settings->system_name ?? 'System' }}</p>
        </div>
        
        <div class="content">
            <h2>Archive Summary for {{ $results['year'] }}</h2>
            
            <div class="stats">
                <p><strong>📊 Total Invoices:</strong> {{ $results['total_invoices'] ?? 0 }}</p>
                <p><strong class="success">✅ Paid Archived:</strong> {{ $results['paid_archived'] ?? 0 }}</p>
                <p><strong class="warning">📌 Unpaid Kept:</strong> {{ $results['unpaid_kept'] ?? 0 }}</p>
                @if(($results['errors'] ?? 0) > 0)
                    <p><strong class="error">❌ Errors:</strong> {{ $results['errors'] }}</p>
                @endif
            </div>
            
            @if(!empty($results['details']['paid_invoices']))
            <div class="stats">
                <h3>Archived Paid Invoices:</h3>
                <ul>
                    @foreach($results['details']['paid_invoices'] as $invoice)
                        <li>#{{ $invoice['invoice_number'] }} - {{ $settings->formatAmount($invoice['amount']) }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
            
            @if(!empty($results['details']['unpaid_invoices']))
            <div class="stats">
                <h3>Unpaid Invoices (Kept Active):</h3>
                <ul>
                    @foreach($results['details']['unpaid_invoices'] as $invoice)
                        <li>#{{ $invoice['invoice_number'] }} - {{ $settings->formatAmount($invoice['amount']) }} (Due: {{ $invoice['due_date'] }})</li>
                    @endforeach
                </ul>
            </div>
            @endif
            
            <div class="footer">
                <p>Report generated on {{ now()->format('F j, Y H:i:s') }}</p>
                <p>&copy; {{ date('Y') }} {{ $settings->system_name ?? 'System' }}. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>