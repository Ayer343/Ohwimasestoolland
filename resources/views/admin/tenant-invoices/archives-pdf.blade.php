<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Archives Export</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            font-size: 10px;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
        }
        .summary {
            margin-bottom: 20px;
            padding: 10px;
            background: #f5f5f5;
        }
        .summary-item {
            display: inline-block;
            margin-right: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 8px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .status-paid { color: #10b981; }
        .status-pending { color: #f59e0b; }
        .status-overdue { color: #ef4444; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">{{ $settings->system_name ?? 'Community Development Association' }}</div>
        <div>Archived Invoices Export</div>
        <div>Generated: {{ $export_date }}</div>
    </div>
    
    <div class="summary">
        <div class="summary-item"><strong>Total Records:</strong> {{ $total_records }}</div>
        <div class="summary-item"><strong>Total Amount:</strong> {{ $settings->formatAmount($total_amount) }}</div>
        <div class="summary-item"><strong>Total Penalties:</strong> {{ $settings->formatAmount($total_penalties) }}</div>
        <div class="summary-item"><strong>Exported By:</strong> {{ $exported_by }}</div>
    </div>
    
     <table>
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Tenant</th>
                <th>Period</th>
                <th>Due Date</th>
                <th>Total Amount</th>
                <th>Status</th>
                <th>Deleted At</th>
                <th>Deleted By</th>
            </tr>
        </thead>
        <tbody>
            @foreach($archives as $archive)
            <tr>
                <td>{{ $archive->invoice_number }}</td>
                <td>{{ $archive->tenant_name }}</td>
                <td>{{ $archive->month_name }}</td>
                <td>{{ $archive->due_date->format('Y-m-d') }}</td>
                <td>{{ $settings->formatAmount($archive->total_amount) }}</td>
                <td class="status-{{ $archive->status }}">{{ strtoupper($archive->status) }}</td>
                <td>{{ $archive->deleted_at->format('Y-m-d H:i') }}</td>
                <td>{{ $archive->deleted_by_name }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    
    <div class="footer">
        <p>This is a computer-generated archive export. For audit purposes only.</p>
        <p>{{ $settings->system_name ?? 'Community Development Association' }} &copy; {{ date('Y') }}</p>
    </div>
</body>
</html>