<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice Report - {{ now()->format('F Y') }}</title>
    <style>
        /* Reset styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            padding: 20px;
        }
        
        /* Header Section */
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #4F46E5;
            padding-bottom: 20px;
        }
        
        .logo {
            max-height: 80px;
            margin-bottom: 10px;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #4F46E5;
            margin-bottom: 5px;
        }
        
        .report-title {
            font-size: 18px;
            font-weight: bold;
            color: #666;
            margin-top: 10px;
        }
        
        .report-date {
            font-size: 11px;
            color: #999;
            margin-top: 5px;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        
        .stat-card {
            flex: 1;
            min-width: 150px;
            padding: 12px;
            background: #f9fafb;
            border-radius: 8px;
            border-left: 3px solid #4F46E5;
        }
        
        .stat-label {
            font-size: 11px;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #111827;
        }
        
        .stat-sub {
            font-size: 10px;
            color: #6B7280;
            margin-top: 3px;
        }
        
        /* Filters Section */
        .filters-section {
            background: #f3f4f6;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 11px;
            page-break-inside: avoid;
        }
        
        .filters-title {
            font-weight: bold;
            margin-bottom: 8px;
            color: #374151;
        }
        
        .filters-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .filter-item {
            background: white;
            padding: 4px 8px;
            border-radius: 4px;
            border: 1px solid #e5e7eb;
        }
        
        .filter-label {
            font-weight: bold;
            color: #6B7280;
        }
        
        /* Table Styles */
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 10px;
        }
        
        .invoice-table th {
            background: #f3f4f6;
            padding: 10px 8px;
            text-align: left;
            font-weight: bold;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .invoice-table td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        
        .invoice-table tr:last-child td {
            border-bottom: none;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
        }
        
        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-pending {
            background: #fed7aa;
            color: #92400e;
        }
        
        .status-overdue {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-consolidated {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        /* Type Badges */
        .type-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
        }
        
        .type-bulk {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .type-regular {
            background: #e5e7eb;
            color: #374151;
        }
        
        /* Amount Styles */
        .amount-positive {
            color: #059669;
            font-weight: bold;
        }
        
        .amount-negative {
            color: #dc2626;
        }
        
        .penalty {
            color: #dc2626;
            font-size: 9px;
        }
        
        /* Summary Section */
        .summary-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
            page-break-inside: avoid;
        }
        
        .summary-grid {
            display: flex;
            justify-content: flex-end;
            gap: 30px;
            margin-top: 15px;
        }
        
        .summary-item {
            text-align: right;
        }
        
        .summary-label {
            font-size: 11px;
            color: #6B7280;
            margin-bottom: 3px;
        }
        
        .summary-value {
            font-size: 16px;
            font-weight: bold;
            color: #111827;
        }
        
        /* Footer */
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #9CA3AF;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
        }
        
        /* Page Break */
        .page-break {
            page-break-before: always;
        }
        
        /* Utilities */
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .font-bold {
            font-weight: bold;
        }
        
        .mt-2 {
            margin-top: 5px;
        }
        
        .mb-2 {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        @if(isset($settings->logo_path) && $settings->logo_path)
            <img src="{{ public_path($settings->logo_path) }}" class="logo" alt="Logo">
        @endif
        <div class="company-name">{{ $settings->company_name ?? 'Property Management System' }}</div>
        <div class="report-title">Invoice Management Report</div>
        <div class="report-date">Generated on: {{ now()->format('F d, Y H:i:s') }}</div>
    </div>

    <!-- Statistics Section -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Invoices</div>
            <div class="stat-value">{{ number_format($statistics['total_invoices'] ?? 0) }}</div>
            <div class="stat-sub">Excluding consolidated</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Paid</div>
            <div class="stat-value">{{ number_format($statistics['paid_invoices'] ?? 0) }}</div>
            <div class="stat-sub">{{ $statistics['collection_rate'] ?? 0 }}% collection rate</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pending</div>
            <div class="stat-value">{{ number_format($statistics['pending_invoices'] ?? 0) }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Overdue</div>
            <div class="stat-value">{{ number_format($statistics['overdue_invoices'] ?? 0) }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value">{{ $settings->formatAmount($statistics['total_revenue'] ?? 0) }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Due</div>
            <div class="stat-value">{{ $settings->formatAmount($statistics['total_due'] ?? 0) }}</div>
        </div>
    </div>

    <!-- Applied Filters -->
    @if(!empty($filters) && (isset($filters['property_id']) || isset($filters['status']) || isset($filters['period'])))
    <div class="filters-section">
        <div class="filters-title">Applied Filters</div>
        <div class="filters-grid">
            @if(isset($filters['property_id']) && $filters['property_id'])
                @php
                    $selectedProperty = \App\Models\Property::find($filters['property_id']);
                @endphp
                @if($selectedProperty)
                <div class="filter-item">
                    <span class="filter-label">Property:</span> 
                    {{ $selectedProperty->house_number }} {{ $selectedProperty->street_name }}
                </div>
                @endif
            @endif
            @if(isset($filters['status']) && $filters['status'])
                <div class="filter-item">
                    <span class="filter-label">Status:</span> 
                    {{ ucfirst($filters['status']) }}
                </div>
            @endif
            @if(isset($filters['type']) && $filters['type'])
                <div class="filter-item">
                    <span class="filter-label">Type:</span> 
                    {{ ucfirst($filters['type']) }}
                </div>
            @endif
            @if(isset($filters['period']) && $filters['period'])
                <div class="filter-item">
                    <span class="filter-label">Period:</span> 
                    {{ \Carbon\Carbon::parse($filters['period'] . '-01')->format('F Y') }}
                </div>
            @endif
            @if(isset($filters['search']) && $filters['search'])
                <div class="filter-item">
                    <span class="filter-label">Search:</span> 
                    {{ $filters['search'] }}
                </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Invoices Table -->
    <table class="invoice-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Invoice #</th>
                <th>Property</th>
                <th>Landlord</th>
                <th>Period</th>
                <th>Due Date</th>
                <th>Amount</th>
                <th>Penalty</th>
                <th>Total</th>
                <th>Status</th>
                <th>Type</th>
                <th>Payment Method</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoices as $index => $invoice)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>
                    <strong>INV-{{ str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</strong>
                    @if($invoice->payment_reference)
                        <br><span style="font-size: 8px;">Ref: {{ $invoice->payment_reference }}</span>
                    @endif
                </td>
                <td>
                    {{ $invoice->property->house_number }} {{ $invoice->property->street_name }}
                    @if($invoice->property->block_number)
                        <br><span style="font-size: 8px;">Block: {{ $invoice->property->block_number }}</span>
                    @endif
                </td>
                <td>{{ $invoice->property->landlord->name }}</td>
                <td>
                    @if($invoice->is_bulk_payment)
                        @if($invoice->bulk_start_month && $invoice->bulk_end_month)
                            {{ \Carbon\Carbon::parse($invoice->bulk_start_month . '-01')->format('M Y') }} - 
                            {{ \Carbon\Carbon::parse($invoice->bulk_end_month . '-01')->format('M Y') }}
                        @else
                            Bulk Payment
                        @endif
                    @else
                        {{ \Carbon\Carbon::parse($invoice->period . '-01')->format('M Y') }}
                    @endif
                </td>
                <td>{{ $invoice->due_date->format('M d, Y') }}</td>
                <td class="amount-positive">{{ $settings->formatAmount($invoice->amount) }}</td>
                <td>
                    @if($invoice->penalty_amount > 0)
                        <span class="penalty">{{ $settings->formatAmount($invoice->penalty_amount) }}</span>
                    @else
                        -
                    @endif
                </td>
                <td class="amount-positive">{{ $settings->formatAmount($invoice->total_amount) }}</td>
                <td>
                    @php
                        $statusClass = match($invoice->status) {
                            'paid' => 'status-paid',
                            'pending' => 'status-pending',
                            'overdue' => 'status-overdue',
                            'consolidated' => 'status-consolidated',
                            default => ''
                        };
                    @endphp
                    <span class="status-badge {{ $statusClass }}">
                        {{ $invoice->status == 'consolidated' ? 'In Bulk' : ucfirst($invoice->status) }}
                    </span>
                </td>
                <td>
                    @if($invoice->is_bulk_payment)
                        <span class="type-badge type-bulk">Bulk</span>
                    @elseif($invoice->bulk_parent_id)
                        <span class="type-badge" style="background:#e0e7ff; color:#3730a3;">Child</span>
                    @else
                        <span class="type-badge type-regular">Regular</span>
                    @endif
                </td>
                <td>
                    @if($invoice->payment_method)
                        {{ ucfirst(str_replace('_', ' ', $invoice->payment_method)) }}
                    @else
                        -
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="12" class="text-center" style="padding: 40px;">
                    <p>No invoices found matching the criteria.</p>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Summary Section -->
    <div class="summary-section">
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">Total Invoices</div>
                <div class="summary-value">{{ number_format($invoices->count()) }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Total Amount</div>
                <div class="summary-value">{{ $settings->formatAmount($invoices->sum('total_amount')) }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Total Paid</div>
                <div class="summary-value">{{ $settings->formatAmount($invoices->where('status', 'paid')->sum('total_amount')) }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Total Penalties</div>
                <div class="summary-value">{{ $settings->formatAmount($invoices->sum('penalty_amount')) }}</div>
            </div>
        </div>
        
        @if($invoices->where('status', 'overdue')->count() > 0)
        <div style="margin-top: 15px; padding: 10px; background: #fee2e2; border-radius: 6px;">
            <strong style="color: #991b1b;">⚠️ Overdue Invoices:</strong> 
            {{ $invoices->where('status', 'overdue')->count() }} invoices totaling 
            {{ $settings->formatAmount($invoices->where('status', 'overdue')->sum('total_amount')) }} are past due.
        </div>
        @endif
        
        @if($invoices->where('is_bulk_payment', true)->where('status', 'paid')->count() > 0)
        <div style="margin-top: 10px; padding: 10px; background: #e0e7ff; border-radius: 6px;">
            <strong style="color: #3730a3;">📦 Active Bulk Coverage:</strong> 
            {{ $invoices->where('is_bulk_payment', true)->where('status', 'paid')->count() }} bulk invoices with active coverage.
        </div>
        @endif
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>This is a computer-generated document. No signature is required.</p>
        <p>© {{ date('Y') }} {{ $settings->company_name ?? 'Property Management System' }}. All rights reserved.</p>
        <p>Generated on: {{ now()->format('F d, Y H:i:s') }} | Page 1 of 1</p>
    </div>
</body>
</html>