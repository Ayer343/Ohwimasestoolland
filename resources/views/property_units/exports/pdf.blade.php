<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Property Units Export</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            padding: 20px;
            margin: 0;
            color: #333;
        }
        h1 {
            font-size: 18px;
            margin-bottom: 5px;
            color: #1a202c;
        }
        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #2d3748;
            padding-bottom: 10px;
        }
        .header .subtitle {
            font-size: 12px;
            color: #718096;
        }
        .summary {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f7fafc;
            border-radius: 5px;
        }
        .summary-table {
            width: auto;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 5px 15px 5px 0;
            font-size: 11px;
        }
        .summary-table .label {
            font-weight: bold;
            color: #4a5568;
        }
        .summary-table .value {
            color: #2d3748;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #2d3748;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td {
            padding: 6px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9px;
        }
        tr:nth-child(even) {
            background-color: #f7fafc;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-available { background-color: #48bb78; color: white; }
        .status-occupied { background-color: #4299e1; color: white; }
        .status-maintenance { background-color: #ecc94b; color: #1a202c; }
        .status-reserved { background-color: #9f7aea; color: white; }
        .footer {
            margin-top: 20px;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            font-size: 9px;
            color: #718096;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Property Units Export</h1>
        <div class="subtitle">
            Exported on: {{ $exported_at }} | Total Units: {{ $total_units }}
        </div>
    </div>

    <div class="summary">
        <table class="summary-table">
            <tr>
                <td class="label">Status Summary:</td>
                @foreach($status_summary as $status => $count)
                <td class="value">
                    <span class="status-badge status-{{ $status }}">
                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                    </span>
                    : {{ $count }}
                </td>
                @endforeach
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>Unit #</th>
                <th>Unit Name</th>
                <th>Property</th>
                <th>Type</th>
                <th>Status</th>
                <th>Rent (GHS)</th>
                <th>Deposit (GHS)</th>
                <th>Beds</th>
                <th>Baths</th>
                <th>Area (sq ft)</th>
                <th>Furnished</th>
                <th>Tenant</th>
                <th>Tenant Status</th>
                <th>Move-in Date</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            @foreach($units as $unit)
            <tr>
                <td><strong>{{ $unit->unit_number }}</strong></td>
                <td>{{ $unit->unit_name ?? 'N/A' }}</td>
                <td>{{ $unit->property->property_name ?? 'N/A' }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $unit->unit_type ?? 'N/A')) }}</td>
                <td>
                    <span class="status-badge status-{{ $unit->status }}">
                        {{ ucfirst(str_replace('_', ' ', $unit->status)) }}
                    </span>
                </td>
                <td>{{ number_format($unit->monthly_rent ?? 0, 2) }}</td>
                <td>{{ number_format($unit->security_deposit ?? 0, 2) }}</td>
                <td>{{ $unit->bedrooms ?? 0 }}</td>
                <td>{{ $unit->bathrooms ?? 0 }}</td>
                <td>{{ $unit->floor_area ?? 'N/A' }}</td>
                <td>{{ $unit->is_furnished ? 'Yes' : 'No' }}</td>
                <td>{{ $unit->tenant ? $unit->tenant->name : 'N/A' }}</td>
                <td>{{ $unit->tenant_status ? ucfirst(str_replace('_', ' ', $unit->tenant_status)) : 'N/A' }}</td>
                <td>{{ $unit->tenant_move_in_date ? $unit->tenant_move_in_date->format('Y-m-d') : 'N/A' }}</td>
                <td>{{ $unit->created_at->format('Y-m-d') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        This report was generated automatically on {{ $exported_at }}.
        All data is for informational purposes only.
    </div>
</body>
</html>