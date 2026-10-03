<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice Report - {{ now()->format('F Y') }}</title>
    
    <!-- ============ FAVICON ============ -->
    @php
        $settings = \App\Models\SystemSetting::getSettings();
    @endphp
    @if($settings->hasFavicon())
        <link rel="icon" href="{{ $settings->getFaviconUrl() }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ $settings->getFaviconUrl() }}" type="image/x-icon">
        <link rel="apple-touch-icon" href="{{ $settings->getFaviconUrl() }}">
        <!-- Additional favicon sizes for better browser support -->
        <link rel="icon" type="image/png" sizes="16x16" href="{{ $settings->getFaviconUrl() }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ $settings->getFaviconUrl() }}">
        <link rel="icon" type="image/png" sizes="64x64" href="{{ $settings->getFaviconUrl() }}">
        <!-- Microsoft Edge Tile -->
        <meta name="msapplication-TileImage" content="{{ $settings->getFaviconUrl() }}">
        <meta name="msapplication-TileColor" content="#4F46E5">
    @else
        <!-- Default favicon fallback -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <meta name="msapplication-TileColor" content="#4F46E5">
    @endif
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', 'Arial', 'Helvetica', sans-serif;
            font-size: 10px;
            line-height: 1.5;
            margin: 0;
            padding: 20px;
            color: #1f2937;
            background: #fff;
        }
        
        /* Main Container */
        .report-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
        }
        
        /* Typography */
        h1 {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            color: #1f2937;
        }
        
        h2 {
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 12px 0;
            color: #374151;
            border-left: 4px solid #4F46E5;
            padding-left: 12px;
        }
        
        h3 {
            font-size: 14px;
            font-weight: 600;
            margin: 0 0 8px 0;
            color: #4b5563;
        }
        
        /* Header Section */
        .header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .company-details h1 {
            font-size: 24px;
            color: #4F46E5;
            margin-bottom: 5px;
        }
        
        .company-tagline {
            color: #6b7280;
            font-size: 11px;
            letter-spacing: 1px;
        }
        
        .report-meta {
            text-align: right;
        }
        
        .report-title {
            font-size: 20px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 8px;
        }
        
        .report-date {
            color: #6b7280;
            font-size: 10px;
        }
        
        /* Company Info Bar */
        .company-info-bar {
            background: #f9fafb;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid #e5e7eb;
            font-size: 9px;
            color: #4b5563;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .info-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .info-label {
            font-weight: 600;
            color: #374151;
        }
        
        /* Report Info Card */
        .report-info-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        
        .info-field {
            display: flex;
            flex-direction: column;
        }
        
        .info-field-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6b7280;
            margin-bottom: 5px;
        }
        
        .info-field-value {
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
        }
        
        /* Statistics - HORIZONTAL FLEX LAYOUT */
        .stats-wrapper {
            margin-bottom: 30px;
            width: 100%;
        }
        
        .stats-horizontal {
            display: flex !important;
            flex-direction: row !important;
            justify-content: space-between !important;
            gap: 15px !important;
            width: 100%;
            margin-bottom: 25px;
        }
        
        .stat-card {
            flex: 1;
            min-width: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px 12px;
            text-align: center;
            transition: all 0.2s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        
        .stat-label {
            font-size: 10px;
            font-weight: 500;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
        }
        
        .stat-value-small {
            font-size: 20px;
        }
        
        /* Filters Section */
        .filters-section {
            background: #fefce8;
            border-left: 3px solid #eab308;
            padding: 12px 16px;
            margin-bottom: 25px;
            border-radius: 6px;
        }
        
        .filters-title {
            font-weight: 600;
            color: #854d0e;
            margin-bottom: 10px;
            font-size: 11px;
            text-transform: uppercase;
        }
        
        .filter-badge {
            display: inline-block;
            padding: 4px 10px;
            margin: 0 6px 6px 0;
            background: white;
            border: 1px solid #fde047;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 500;
            color: #854d0e;
        }
        
        /* Coverage Summary */
        .coverage-summary {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 1px solid #86efac;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 25px;
        }
        
        .coverage-title {
            font-weight: 700;
            color: #166534;
            margin-bottom: 12px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .coverage-property {
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #86efac;
        }
        
        .coverage-property:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .property-name {
            font-weight: 600;
            color: #14532d;
            margin-bottom: 6px;
            font-size: 11px;
        }
        
        .coverage-period {
            display: inline-block;
            padding: 3px 8px;
            background: #dcfce7;
            border-radius: 12px;
            margin: 3px;
            font-size: 8px;
            color: #166534;
        }
        
        /* Table Styles */
        .table-wrapper {
            overflow-x: auto;
            margin-bottom: 25px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
        }
        
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }
        
        .invoice-table th {
            background: #f9fafb;
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .invoice-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }
        
        .invoice-table tr:hover {
            background: #fafafa;
        }
        
        .invoice-table tr:last-child td {
            border-bottom: none;
        }
        
        /* Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
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
        
        .type-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 8px;
            font-weight: 500;
        }
        
        .type-bulk {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .type-regular {
            background: #f3f4f6;
            color: #374151;
        }
        
        .type-child {
            background: #e6e6fa;
            color: #4c51bf;
        }
        
        .covered-badge {
            background: #d1fae5;
            color: #065f46;
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 8px;
            font-weight: 600;
        }
        
        /* Amount Styles */
        .amount-positive {
            font-weight: 600;
            color: #059669;
        }
        
        .penalty-text {
            color: #dc2626;
            font-size: 8px;
        }
        
        .invoice-number {
            font-weight: 600;
            color: #4F46E5;
            font-family: monospace;
        }
        
        /* Property Info */
        .property-name-cell {
            font-weight: 600;
            color: #1f2937;
        }
        
        .property-address {
            font-size: 8px;
            color: #6b7280;
            margin-top: 2px;
        }
        
        /* Summary Section - HORIZONTAL FLEX LAYOUT */
        .summary-section {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
        }
        
        .summary-horizontal {
            display: flex !important;
            flex-direction: row !important;
            justify-content: space-between !important;
            gap: 15px !important;
            width: 100%;
            margin-bottom: 20px;
        }
        
        .summary-card {
            flex: 1;
            min-width: 0;
            background: #f9fafb;
            border-radius: 10px;
            padding: 14px;
            text-align: center;
            border: 1px solid #e5e7eb;
        }
        
        .summary-label {
            font-size: 9px;
            color: #6b7280;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        
        .summary-value {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
        }
        
        /* Notice Boxes */
        .notice-box {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-size: 9px;
        }
        
        .notice-overdue {
            background: #fef2f2;
            border-left: 4px solid #dc2626;
        }
        
        .notice-payment {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
        }
        
        .notice-info {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
        }
        
        .notice-title {
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 10px;
        }
        
        /* Footer */
        .footer {
            margin-top: 35px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 8px;
            color: #9ca3af;
        }
        
        .footer p {
            margin: 4px 0;
        }
        
        /* Utilities */
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .font-bold {
            font-weight: 700;
        }
        
        /* Page Break */
        .page-break {
            page-break-before: always;
        }
        
        /* Print Optimization */
        @media print {
            body {
                padding: 0;
                margin: 0;
            }
            
            .stat-card, .summary-card, .notice-box {
                break-inside: avoid;
            }
            
            .invoice-table tr {
                break-inside: avoid;
            }
        }
        
        /* Responsive - Stack on smaller screens */
        @media (max-width: 768px) {
            .stats-horizontal, .summary-horizontal {
                flex-direction: column !important;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <!-- Header Section -->
        <div class="header">
            <div class="header-top">
                <div class="company-details">
                    <h1>{{ $settings->system_name ?? 'Property Management System' }}</h1>
                    <div class="company-tagline">Professional Property Management Solutions</div>
                </div>
                <div class="report-meta">
                    <div class="report-title">INVOICE MANAGEMENT REPORT</div>
                    <div class="report-date">Generated: {{ now()->format('F d, Y \a\t H:i:s') }}</div>
                </div>
            </div>
        </div>
        
        <!-- Company Information Bar -->
        <div class="company-info-bar">
            @if($settings->system_email)
                <div class="info-item">
                    <span class="info-label">Email:</span>
                    <span>{{ $settings->system_email }}</span>
                </div>
            @endif
            @if($settings->system_phone)
                <div class="info-item">
                    <span class="info-label">Phone:</span>
                    <span>{{ $settings->system_phone }}</span>
                </div>
            @endif
            @if($settings->system_address)
                <div class="info-item">
                    <span class="info-label">Address:</span>
                    <span>{{ $settings->system_address }}</span>
                </div>
            @endif
            @if($settings->currency_code)
                <div class="info-item">
                    <span class="info-label">Currency:</span>
                    <span>{{ $settings->currency_code }}</span>
                </div>
            @endif
        </div>
        
        <!-- Report Information Card -->
        <div class="report-info-card">
            <div class="info-grid">
                <div class="info-field">
                    <div class="info-field-label">Report Type</div>
                    <div class="info-field-value">Landlord Invoices</div>
                </div>
                <div class="info-field">
                    <div class="info-field-label">Landlord</div>
                    <div class="info-field-value">{{ auth()->user()->name }}</div>
                </div>
                <div class="info-field">
                    <div class="info-field-label">Report Period</div>
                    <div class="info-field-value">{{ now()->format('F Y') }}</div>
                </div>
                <div class="info-field">
                    <div class="info-field-label">Total Invoices</div>
                    <div class="info-field-value">{{ $invoices->count() }}</div>
                </div>
            </div>
        </div>
        
        <!-- Statistics Dashboard - HORIZONTAL LAYOUT -->
        <div class="stats-wrapper">
            <h2>Financial Overview</h2>
            <div class="stats-horizontal">
                <div class="stat-card">
                    <div class="stat-label">Total Invoices</div>
                    <div class="stat-value">{{ $statistics['total_invoices'] ?? $invoices->count() }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Paid</div>
                    <div class="stat-value">{{ $statistics['paid_invoices'] ?? $invoices->where('status', 'paid')->count() }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pending</div>
                    <div class="stat-value">{{ $statistics['pending_invoices'] ?? $invoices->where('status', 'pending')->count() }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Overdue</div>
                    <div class="stat-value">{{ $statistics['overdue_invoices'] ?? $invoices->where('status', 'overdue')->count() }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Amount</div>
                    <div class="stat-value stat-value-small">{{ $settings->safeFormatAmount($invoices->sum('total_amount')) }}</div>
                </div>
            </div>
        </div>
        
        <!-- Applied Filters -->
        @if(!empty($filters) && (isset($filters['property_id']) || isset($filters['status']) || isset($filters['type'])))
        <div class="filters-section">
            <div class="filters-title">📋 Applied Filters</div>
            <div>
                @if(isset($filters['property_id']) && $filters['property_id'])
                    @php
                        $selectedProperty = $properties->firstWhere('id', $filters['property_id']);
                    @endphp
                    <span class="filter-badge">🏢 Property: {{ $selectedProperty ? ($selectedProperty->property_name ?? $selectedProperty->street_name) : 'N/A' }}</span>
                @endif
                @if(isset($filters['status']) && $filters['status'])
                    <span class="filter-badge">📊 Status: {{ ucfirst($filters['status']) }}</span>
                @endif
                @if(isset($filters['type']) && $filters['type'])
                    <span class="filter-badge">📄 Type: {{ ucfirst($filters['type']) }}</span>
                @endif
                @if(isset($filters['coverage']) && $filters['coverage'])
                    <span class="filter-badge">🛡️ Coverage: {{ ucfirst($filters['coverage']) }}</span>
                @endif
            </div>
        </div>
        @endif
        
        <!-- Active Coverage Summary -->
        @if(isset($bulkCoverages) && is_array($bulkCoverages) && count($bulkCoverages) > 0)
        <div class="coverage-summary">
            <div class="coverage-title">
                🛡️ Active Bulk Coverage Summary
            </div>
            @foreach($bulkCoverages as $propertyId => $coverages)
                @if(is_array($coverages) && count($coverages) > 0)
                    @php
                        $property = $properties->firstWhere('id', $propertyId);
                    @endphp
                    <div class="coverage-property">
                        <div class="property-name">{{ $property->property_name ?? ($property->street_name ?? 'Property') }}</div>
                        @foreach($coverages as $coverage)
                            @php
                                $periods = $coverage['periods'] ?? [];
                                $formattedPeriods = collect($periods)->map(function($p) {
                                    return \Carbon\Carbon::parse($p . '-01')->format('M Y');
                                })->toArray();
                            @endphp
                            <div>
                                @foreach(array_slice($formattedPeriods, 0, 5) as $period)
                                    <span class="coverage-period">{{ $period }}</span>
                                @endforeach
                                @if(count($formattedPeriods) > 5)
                                    <span class="coverage-period">+{{ count($formattedPeriods) - 5 }} more</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            @endforeach
            <div class="notice-info" style="margin-top: 12px; padding: 8px; background: #e6f7e6;">
                ℹ️ No invoices will be generated for the periods listed above as they are covered by bulk payments.
            </div>
        </div>
        @endif
        
        <!-- Invoices Section -->
        <h2>Invoice Details</h2>
        <div class="table-wrapper">
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th width="5%">#</th>
                        <th width="12%">Invoice #</th>
                        <th width="18%">Property</th>
                        <th width="12%">Period</th>
                        <th width="10%">Due Date</th>
                        <th width="8%">Amount</th>
                        <th width="8%">Penalty</th>
                        <th width="10%">Total</th>
                        <th width="10%">Status</th>
                        <th width="7%">Type</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $index => $invoice)
                    @php
                        $isCovered = false;
                        if(isset($bulkCoverages) && isset($bulkCoverages[$invoice->property_id])) {
                            foreach($bulkCoverages[$invoice->property_id] as $coverage) {
                                if(in_array($invoice->period, $coverage['periods'] ?? [])) {
                                    $isCovered = true;
                                    break;
                                }
                            }
                        }
                        
                        $periodDisplay = $invoice->period;
                        if($invoice->is_bulk_payment && $invoice->bulk_coverage_start && $invoice->bulk_coverage_end) {
                            $periodDisplay = \Carbon\Carbon::parse($invoice->bulk_coverage_start . '-01')->format('M Y') . ' - ' . 
                                            \Carbon\Carbon::parse($invoice->bulk_coverage_end . '-01')->format('M Y');
                        } elseif(preg_match('/^\d{4}-\d{2}$/', $invoice->period)) {
                            $periodDisplay = \Carbon\Carbon::parse($invoice->period . '-01')->format('M Y');
                        }
                        
                        $statusClass = match($invoice->status) {
                            'paid' => 'status-paid',
                            'pending' => 'status-pending',
                            'overdue' => 'status-overdue',
                            'consolidated' => 'status-consolidated',
                            default => ''
                        };
                        
                        $typeClass = match(true) {
                            $invoice->is_bulk_payment => 'type-bulk',
                            $invoice->bulk_payment_id => 'type-child',
                            default => 'type-regular'
                        };
                        
                        $typeText = match(true) {
                            $invoice->is_bulk_payment => 'Bulk',
                            $invoice->bulk_payment_id => 'Child',
                            default => 'Regular'
                        };
                    @endphp
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td class="invoice-number">{{ $invoice->invoice_number ?? 'INV-'.str_pad($invoice->id, 6, '0', STR_PAD_LEFT) }}</td>
                        <td>
                            @if($invoice->property)
                                <div class="property-name-cell">{{ $invoice->property->property_name ?? 'Property' }}</div>
                                <div class="property-address">
                                    {{ $invoice->property->house_number ?? '' }} {{ $invoice->property->street_name ?? '' }}
                                </div>
                            @else
                                <span class="property-address">Property not found</span>
                            @endif
                        </td>
                        <td>
                            {{ $periodDisplay }}
                            @if($invoice->is_bulk_payment && $invoice->bulk_months)
                                <div class="property-address">({{ $invoice->bulk_months }} months)</div>
                            @endif
                        </td>
                        <td>{{ \Carbon\Carbon::parse($invoice->due_date)->format('d M Y') }}</td>
                        <td class="amount-positive">{{ $settings->safeFormatAmount($invoice->amount) }}</td>
                        <td>
                            @if($invoice->penalty_amount > 0)
                                <span class="penalty-text">{{ $settings->safeFormatAmount($invoice->penalty_amount) }}</span>
                            @else
                                <span class="property-address">—</span>
                            @endif
                        </td>
                        <td class="amount-positive"><strong>{{ $settings->safeFormatAmount($invoice->total_amount) }}</strong></td>
                        <td>
                            @if($isCovered && $invoice->status != 'paid')
                                <span class="covered-badge">
                                    🛡️ Covered
                                </span>
                            @else
                                <span class="status-badge {{ $statusClass }}">
                                    {{ ucfirst($invoice->status) }}
                                </span>
                            @endif
                        </td>
                        <td>
                            <span class="type-badge {{ $typeClass }}">
                                {{ $typeText }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="text-center" style="padding: 60px 20px;">
                            <div style="color: #6b7280;">
                                <div style="font-size: 48px; margin-bottom: 10px;">📄</div>
                                <div style="font-size: 14px; font-weight: 500;">No invoices found</div>
                                <div style="font-size: 10px; margin-top: 5px;">No invoices match the current filters or criteria</div>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Summary Section - HORIZONTAL LAYOUT -->
        <div class="summary-section">
            <h2>Financial Summary</h2>
            <div class="summary-horizontal">
                <div class="summary-card">
                    <div class="summary-label">Total Invoices</div>
                    <div class="summary-value">{{ $invoices->count() }}</div>
                </div>
                <div class="summary-card">
                    <div class="summary-label">Total Amount</div>
                    <div class="summary-value">{{ $settings->safeFormatAmount($invoices->sum('total_amount')) }}</div>
                </div>
                <div class="summary-card">
                    <div class="summary-label">Total Paid</div>
                    <div class="summary-value">{{ $settings->safeFormatAmount($invoices->where('status', 'paid')->sum('total_amount')) }}</div>
                </div>
                <div class="summary-card">
                    <div class="summary-label">Outstanding Balance</div>
                    <div class="summary-value">{{ $settings->safeFormatAmount($invoices->whereIn('status', ['pending', 'overdue'])->sum('total_amount')) }}</div>
                </div>
            </div>
            
            <!-- Overdue Notice -->
            @if($invoices->where('status', 'overdue')->count() > 0)
            <div class="notice-box notice-overdue">
                <div class="notice-title">⚠️ OVERDUE INVOICES ALERT</div>
                <div>
                    You have <strong>{{ $invoices->where('status', 'overdue')->count() }} overdue invoice(s)</strong> totaling 
                    <strong>{{ $settings->safeFormatAmount($invoices->where('status', 'overdue')->sum('total_amount')) }}</strong>.
                    Late payment penalties may apply as per the payment terms. Please settle these amounts immediately to avoid additional charges.
                </div>
            </div>
            @endif
            
            <!-- Payment Instructions -->
            @if($invoices->whereIn('status', ['pending', 'overdue'])->count() > 0)
            <div class="notice-box notice-payment">
                <div class="notice-title">💰 Payment Instructions</div>
                <div style="margin-bottom: 8px;">
                    @if($settings->payment_mobile_number)
                        <strong>Mobile Money:</strong> {{ $settings->payment_mobile_number }} 
                        ({{ strtoupper($settings->payment_network ?? 'All Networks') }})<br>
                        <strong>Account Name:</strong> {{ $settings->payment_account_name ?? 'Property Management' }}<br>
                    @endif
                    @if($settings->bank_name && $settings->bank_account_number)
                        <strong>Bank Transfer:</strong> {{ $settings->bank_name }} - {{ $settings->bank_account_number }}<br>
                    @endif
                </div>
                <div style="font-size: 8px; color: #92400e;">
                    <strong>Important:</strong> Please use the invoice number as reference when making payment to ensure proper allocation.
                </div>
            </div>
            @endif
            
            <!-- Additional Information -->
            <div class="notice-box notice-info">
                <div class="notice-title">📌 Important Information</div>
                <div>
                    • This report includes all invoices generated during the selected period.<br>
                    • Invoices marked as "Covered" are protected by bulk payment arrangements.<br>
                    • For any discrepancies, please contact support within 7 days of receipt.<br>
                    • Payment receipts are available upon request after settlement.
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>This is a computer-generated document. No signature is required.</p>
            <p>© {{ date('Y') }} {{ $settings->system_name ?? 'Property Management System' }}. All rights reserved.</p>
            <p>Generated on: {{ now()->format('F d, Y \a\t H:i:s') }} | Report ID: {{ uniqid('INV-RPT-') }}</p>
        </div>
    </div>
</body>
</html>