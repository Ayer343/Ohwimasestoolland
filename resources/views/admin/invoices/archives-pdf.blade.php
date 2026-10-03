<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Invoices Export</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', 'Segoe UI', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #333;
            padding: 15px;
        }
        
        .container {
            max-width: 100%;
            margin: 0 auto;
        }
        
        /* Header */
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ddd;
        }
        
        .header h1 {
            font-size: 20px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .header h2 {
            font-size: 14px;
            color: #7f8c8d;
            font-weight: normal;
        }
        
        /* Summary Section */
        .summary-section {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
        }
        
        .summary-title {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #2c3e50;
        }
        
        .summary-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }
        
        .summary-row {
            display: table-row;
        }
        
        .summary-label {
            display: table-cell;
            width: 25%;
            padding: 4px;
            font-weight: bold;
        }
        
        .summary-value {
            display: table-cell;
            width: 25%;
            padding: 4px;
        }
        
        /* Table Styles */
        .archives-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 8px;
        }
        
        .archives-table th {
            background-color: #ecf0f1;
            padding: 6px 4px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
            font-size: 8px;
        }
        
        .archives-table td {
            padding: 4px;
            border: 1px solid #ddd;
            font-size: 8px;
        }
        
        .archives-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 4px;
            border-radius: 2px;
            font-size: 7px;
            font-weight: bold;
        }
        
        .status-paid { background-color: #d4edda; color: #155724; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-overdue { background-color: #f8d7da; color: #721c24; }
        
        .text-right {
            text-align: right;
        }
        
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 8px;
            color: #7f8c8d;
        }
        
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>{{ $settings->system_name ?? 'Property Management System' }}</h1>
            <h2>Archived Invoices Report</h2>
            <p>Generated on {{ $export_date }}</p>
            <p>Exported by: {{ $exported_by }}</p>
        </div>
        
        <!-- Summary Section -->
        <div class="summary-section">
            <div class="summary-title">Export Summary</div>
            <div class="summary-grid">
                <div class="summary-row">
                    <div class="summary-label">Total Records:</div>
                    <div class="summary-value">{{ number_format($total_records) }}</div>
                    <div class="summary-label">Total Amount:</div>
                    <div class="summary-value">{{ $settings->formatAmount($total_amount) }}</div>
                </div>
                <div class="summary-row">
                    <div class="summary-label">Total Penalties:</div>
                    <div class="summary-value">{{ $settings->formatAmount($total_penalties ?? 0) }}</div>
                    <div class="summary-label">Average Amount:</div>
                    <div class="summary-value">{{ $total_records > 0 ? $settings->formatAmount($total_amount / $total_records) : $settings->formatAmount(0) }}</div>
                </div>
            </div>
        </div>
        
        <!-- Archives Table -->
        <table class="archives-table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Property</th>
                    <th>Landlord</th>
                    <th>Period</th>
                    <th>Amount</th>
                    <th>Penalty</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Archive Type</th>
                    <th>Deleted At</th>
                    <th>Deleted By</th>
                </tr>
            </thead>
            <tbody>
                @forelse($archives as $archive)
                <tr>
                    <td>{{ $archive->invoice_number }}</td>
                    <td>{{ $archive->property_name }}</td>
                    <td>{{ $archive->landlord_name }}</td>
                    <td>{{ $archive->month_name ?? $archive->period }}</td>
                    <td class="text-right">{{ $settings->formatAmount($archive->amount) }}</td>
                    <td class="text-right">{{ $settings->formatAmount($archive->penalty_amount) }}</td>
                    <td class="text-right">{{ $settings->formatAmount($archive->total_amount) }}</td>
                    <td>
                        <span class="status-badge status-{{ $archive->status }}">
                            {{ ucfirst($archive->status) }}
                        </span>
                    </td>
                    <td>{{ ucfirst(str_replace('_', ' ', $archive->archive_type)) }}</td>
                    <td>{{ $archive->deleted_at ? \Carbon\Carbon::parse($archive->deleted_at)->format('Y-m-d') : 'N/A' }}</td>
                    <td>{{ $archive->deleted_by_name }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="11" class="text-center">No archived invoices found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        
        <!-- Footer -->
        <div class="footer">
            <p>This report contains archived invoice records for audit purposes.</p>
            <p>Total Records: {{ number_format($total_records) }} | Total Value: {{ $settings->formatAmount($total_amount) }}</p>
            <p>Generated by {{ $exported_by }} on {{ $export_date }}</p>
        </div>
    </div>
</body>
</html>