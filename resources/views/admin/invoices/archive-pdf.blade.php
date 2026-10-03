<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive Invoice - {{ $archive->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', 'Segoe UI', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            padding: 20px;
        }
        
        .container {
            max-width: 100%;
            margin: 0 auto;
        }
        
        /* Header Styles */
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ddd;
        }
        
        .header h1 {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .header h2 {
            font-size: 18px;
            color: #7f8c8d;
            font-weight: normal;
        }
        
        .company-info {
            text-align: center;
            margin-bottom: 20px;
            font-size: 11px;
            color: #7f8c8d;
        }
        
        /* Title */
        .archive-title {
            background-color: #3498db;
            color: white;
            padding: 8px 12px;
            margin-bottom: 20px;
            font-weight: bold;
            font-size: 14px;
            text-align: center;
        }
        
        /* Info Sections */
        .info-section {
            margin-bottom: 20px;
        }
        
        .section-title {
            background-color: #ecf0f1;
            padding: 6px 10px;
            font-weight: bold;
            font-size: 13px;
            border-left: 3px solid #3498db;
            margin-bottom: 10px;
        }
        
        .info-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }
        
        .info-row {
            display: table-row;
        }
        
        .info-label {
            display: table-cell;
            width: 30%;
            padding: 6px 10px;
            font-weight: bold;
            background-color: #f9f9f9;
            border-bottom: 1px solid #eee;
        }
        
        .info-value {
            display: table-cell;
            width: 70%;
            padding: 6px 10px;
            border-bottom: 1px solid #eee;
        }
        
        /* Financial Summary */
        .financial-summary {
            margin-top: 20px;
            margin-bottom: 20px;
        }
        
        .financial-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
            background-color: #f8f9fa;
        }
        
        .financial-row {
            display: table-row;
        }
        
        .financial-label {
            display: table-cell;
            width: 50%;
            padding: 8px 12px;
            font-weight: bold;
            border: 1px solid #ddd;
        }
        
        .financial-value {
            display: table-cell;
            width: 50%;
            padding: 8px 12px;
            text-align: right;
            font-weight: bold;
            border: 1px solid #ddd;
        }
        
        .total-row {
            background-color: #e8f4f8;
        }
        
        .total-row .financial-label,
        .total-row .financial-value {
            font-size: 14px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .status-paid { background-color: #d4edda; color: #155724; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-overdue { background-color: #f8d7da; color: #721c24; }
        .status-partial { background-color: #d1ecf1; color: #0c5460; }
        .status-cancelled { background-color: #e2e3e5; color: #383d41; }
        
        /* Deletion Info */
        .deletion-info {
            background-color: #fef5e7;
            padding: 10px;
            border-left: 3px solid #e67e22;
            margin-top: 15px;
        }
        
        .deletion-label {
            font-weight: bold;
            color: #e67e22;
            font-size: 11px;
            margin-bottom: 5px;
        }
        
        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #7f8c8d;
        }
        
        /* Bulk Coverage */
        .coverage-periods {
            margin-top: 10px;
        }
        
        .coverage-badge {
            display: inline-block;
            background-color: #e8f4f8;
            color: #3498db;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            margin: 2px;
        }
        
        /* Table for items */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .items-table th {
            background-color: #ecf0f1;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
            font-size: 11px;
        }
        
        .items-table td {
            padding: 8px;
            border: 1px solid #ddd;
            font-size: 11px;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 8px;
            margin-top: 15px;
            font-size: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>{{ $settings->system_name ?? 'Property Management System' }}</h1>
            <h2>Archive Invoice Record</h2>
            <div class="company-info">
                {{ $settings->system_address ?? '' }}<br>
                Tel: {{ $settings->system_phone ?? '' }} | Email: {{ $settings->system_email ?? '' }}
            </div>
        </div>
        
        <div class="archive-title">
            ARCHIVED INVOICE - AUDIT RECORD
        </div>
        
        <!-- Basic Information -->
        <div class="info-section">
            <div class="section-title">Basic Information</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Invoice Number:</div>
                    <div class="info-value">{{ $archive->invoice_number }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Original Invoice ID:</div>
                    <div class="info-value">#{{ $archive->original_invoice_id ?? 'N/A' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Period:</div>
                    <div class="info-value">{{ $archive->month_name ?? $archive->period }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Due Date:</div>
                    <div class="info-value">{{ $archive->due_date ? \Carbon\Carbon::parse($archive->due_date)->format('F d, Y') : 'N/A' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Status:</div>
                    <div class="info-value">
                        <span class="status-badge status-{{ $archive->status }}">
                            {{ ucfirst($archive->status) }}
                        </span>
                    </div>
                </div>
                @if($archive->is_bulk_payment)
                <div class="info-row">
                    <div class="info-label">Type:</div>
                    <div class="info-value">
                        <strong>Bulk Payment Invoice</strong>
                    </div>
                </div>
                @endif
            </div>
        </div>
        
        <!-- Property & Landlord Information -->
        <div class="info-section">
            <div class="section-title">Property & Landlord Information</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Property:</div>
                    <div class="info-value">{{ $archive->property_name }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Property ID:</div>
                    <div class="info-value">#{{ $archive->property_id }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Landlord:</div>
                    <div class="info-value">{{ $archive->landlord_name }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Landlord ID:</div>
                    <div class="info-value">#{{ $archive->landlord_id }}</div>
                </div>
            </div>
        </div>
        
        <!-- Financial Summary -->
        <div class="financial-summary">
            <div class="section-title">Financial Summary</div>
            <div class="financial-grid">
                <div class="financial-row">
                    <div class="financial-label">Base Amount:</div>
                    <div class="financial-value">{{ $settings->formatAmount($archive->amount) }}</div>
                </div>
                @if($archive->penalty_amount > 0)
                <div class="financial-row">
                    <div class="financial-label">Penalty Amount:</div>
                    <div class="financial-value" style="color: #dc3545;">{{ $settings->formatAmount($archive->penalty_amount) }}</div>
                </div>
                @endif
                <div class="financial-row">
                    <div class="financial-label">Total Amount:</div>
                    <div class="financial-value">{{ $settings->formatAmount($archive->total_amount) }}</div>
                </div>
                @if($archive->paid_amount > 0)
                <div class="financial-row">
                    <div class="financial-label">Paid Amount:</div>
                    <div class="financial-value" style="color: #28a745;">{{ $settings->formatAmount($archive->paid_amount) }}</div>
                </div>
                @endif
                <div class="financial-row total-row">
                    <div class="financial-label">Balance:</div>
                    <div class="financial-value">{{ $settings->formatAmount($archive->balance) }}</div>
                </div>
            </div>
        </div>
        
        <!-- Payment Information (if paid) -->
        @if($archive->paid_amount > 0)
        <div class="info-section">
            <div class="section-title">Payment Information</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Payment Method:</div>
                    <div class="info-value">{{ ucfirst($archive->payment_method) }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Payment Reference:</div>
                    <div class="info-value">{{ $archive->payment_reference ?? 'N/A' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Payment Date:</div>
                    <div class="info-value">{{ $archive->payment_date ? \Carbon\Carbon::parse($archive->payment_date)->format('F d, Y') : 'N/A' }}</div>
                </div>
            </div>
        </div>
        @endif
        
        <!-- Bulk Coverage Information -->
        @if($archive->covers_periods && count($archive->covers_periods) > 0)
        <div class="info-section">
            <div class="section-title">Bulk Coverage Information</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Coverage Periods:</div>
                    <div class="info-value">
                        <div class="coverage-periods">
                            @foreach($archive->covers_periods as $period)
                                <span class="coverage-badge">{{ \Carbon\Carbon::parse($period . '-01')->format('M Y') }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Total Months Covered:</div>
                    <div class="info-value">{{ count($archive->covers_periods) }}</div>
                </div>
            </div>
        </div>
        @endif
        
        <!-- Deletion Information -->
        <div class="deletion-info">
            <div class="deletion-label">DELETION INFORMATION</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Deleted At:</div>
                    <div class="info-value">{{ $archive->deleted_at ? \Carbon\Carbon::parse($archive->deleted_at)->format('F d, Y H:i:s') : 'N/A' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Deleted By:</div>
                    <div class="info-value">{{ $archive->deleted_by_name }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Deletion Reason:</div>
                    <div class="info-value">{{ $archive->deletion_reason ?? 'No reason provided' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Deletion IP:</div>
                    <div class="info-value">{{ $archive->deletion_ip ?? 'N/A' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Archive Type:</div>
                    <div class="info-value">{{ ucfirst(str_replace('_', ' ', $archive->archive_type)) }}</div>
                </div>
            </div>
        </div>
        
        <!-- Additional Information -->
        @if($archive->description || $archive->notes)
        <div class="info-section">
            <div class="section-title">Additional Information</div>
            <div class="info-grid">
                @if($archive->description)
                <div class="info-row">
                    <div class="info-label">Description:</div>
                    <div class="info-value">{{ $archive->description }}</div>
                </div>
                @endif
                @if($archive->notes)
                <div class="info-row">
                    <div class="info-label">Notes:</div>
                    <div class="info-value">{{ $archive->notes }}</div>
                </div>
                @endif
            </div>
        </div>
        @endif
        
        <!-- Original Creation Information -->
        <div class="info-section">
            <div class="section-title">Original Creation Information</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Created At:</div>
                    <div class="info-value">{{ $archive->original_created_at ? \Carbon\Carbon::parse($archive->original_created_at)->format('F d, Y H:i:s') : 'N/A' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Created By:</div>
                    <div class="info-value">{{ $archive->original_created_by ? 'User #' . $archive->original_created_by : 'System' }}</div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>This is an archived invoice record generated for audit purposes.</p>
            <p>Generated on {{ now()->format('F d, Y H:i:s') }} by {{ auth()->user()->name }}</p>
            <p>Archive ID: {{ $archive->id }} | Original Invoice: {{ $archive->invoice_number }}</p>
        </div>
    </div>
</body>
</html>