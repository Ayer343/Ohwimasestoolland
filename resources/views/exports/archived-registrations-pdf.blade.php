<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Archived Registrations Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #333;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #4a5568;
            padding-bottom: 15px;
        }
        
        .header h1 {
            margin: 0;
            font-size: 20px;
            color: #2d3748;
        }
        
        .header p {
            margin: 8px 0 0;
            font-size: 10px;
            color: #718096;
        }
        
        .summary {
            margin-bottom: 20px;
            padding: 12px;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        
        .summary table {
            width: 100%;
            font-size: 9px;
        }
        
        .summary td {
            padding: 4px 8px;
        }
        
        .summary td:first-child {
            font-weight: bold;
            width: 140px;
        }
        
        .stats {
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .stat-box {
            flex: 1;
            min-width: 100px;
            padding: 10px;
            background: #edf2f7;
            text-align: center;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
        }
        
        .stat-box .number {
            font-size: 18px;
            font-weight: bold;
            color: #2d3748;
        }
        
        .stat-box .label {
            font-size: 9px;
            color: #718096;
            margin-top: 4px;
        }
        
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 8px;
        }
        
        table.data th {
            background: #4a5568;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #4a5568;
        }
        
        table.data td {
            border: 1px solid #e2e8f0;
            padding: 6px;
            vertical-align: top;
        }
        
        table.data tr:nth-child(even) {
            background: #f7fafc;
        }
        
        .footer {
            margin-top: 25px;
            text-align: center;
            font-size: 8px;
            color: #a0aec0;
            border-top: 1px solid #e2e8f0;
            padding-top: 12px;
        }
        
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 7px;
            font-weight: bold;
        }
        
        .badge-approved {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .badge-rejected {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .badge-pending {
            background: #feebc8;
            color: #744210;
        }
        
        .badge-cancelled {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .text-muted {
            color: #a0aec0;
            font-size: 7px;
        }
        
        @page {
            margin: 2cm;
            footer: html_footer;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📦 Archived Registrations Report</h1>
        <p>Generated: {{ $generated_at ?? now()->format('Y-m-d H:i:s') }} | 
           Exported by: {{ $summary['exported_by'] ?? auth()->user()->name ?? 'System' }}</p>
    </div>

    <div class="summary">
        <table>
            <tr>
                <td>Total Records:</td>
                <td><strong>{{ number_format($summary['total_records'] ?? $registrations->count()) }}</strong></td>
                <td>Date Range:</td>
                <td><strong>{{ $summary['date_range'] ?? 'All Years' }}</strong></td>
            </tr>
            <tr>
                <td>Status Filter:</td>
                <td><strong>{{ $summary['status_filter'] ?? 'All' }}</strong></td>
                <td>Type Filter:</td>
                <td><strong>{{ $summary['type_filter'] ?? 'All' }}</strong></td>
            </tr>
            <tr>
                <td>Export Time:</td>
                <td><strong>{{ now()->format('H:i:s') }}</strong></td>
                <td>Timezone:</td>
                <td><strong>{{ config('app.timezone', 'UTC') }}</strong></td>
            </tr>
        </table>
    </div>

    <div class="stats">
        <div class="stat-box">
            <div class="number">{{ number_format($stats['by_status']['approved'] ?? 0) }}</div>
            <div class="label">✅ Approved</div>
        </div>
        <div class="stat-box">
            <div class="number">{{ number_format($stats['by_status']['rejected'] ?? 0) }}</div>
            <div class="label">❌ Rejected</div>
        </div>
        <div class="stat-box">
            <div class="number">{{ number_format($stats['by_status']['pending'] ?? 0) }}</div>
            <div class="label">⏳ Pending</div>
        </div>
        <div class="stat-box">
            <div class="number">{{ number_format($stats['by_status']['cancelled'] ?? 0) }}</div>
            <div class="label">🚫 Cancelled</div>
        </div>
        <div class="stat-box">
            <div class="number">{{ number_format($stats['total_with_tenants'] ?? 0) }}</div>
            <div class="label">👥 With Tenants</div>
        </div>
    </div>

    @if(!empty($stats['by_year']))
    <div class="summary" style="margin-top: 10px;">
        <table style="width: auto; margin: 0 auto;">
            <tr>
                @foreach($stats['by_year'] as $year => $count)
                <td style="text-align: center; padding: 4px 12px;">
                    <strong>{{ $year }}</strong><br>
                    <span class="text-muted">{{ number_format($count) }} records</span>
                </td>
                @endforeach
            </tr>
        </table>
    </div>
    @endif

    <table class="data">
        <thead>
            <tr>
                <th width="12%">Applicant</th>
                <th width="15%">Property</th>
                <th width="8%">Plot #</th>
                <th width="10%">Phone</th>
                <th width="10%">Status</th>
                <th width="8%">Type</th>
                <th width="7%">Year</th>
                <th width="10%">Archived Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($registrations as $reg)
            <tr>
                <td>
                    <strong>{{ $reg->name ?? 'N/A' }}</strong>
                    @if($reg->email)
                    <br><span class="text-muted">{{ $reg->email }}</span>
                    @endif
                </td>
                <td>
                    {{ $reg->property_name ?? 'N/A' }}
                    @if($reg->street_name)
                    <br><span class="text-muted">{{ $reg->street_name }}</span>
                    @endif
                </td>
                <td>{{ $reg->plot_number ?? 'N/A' }}</td>
                <td>{{ $reg->primary_phone ?? 'N/A' }}</td>
                <td>
                    @php
                        $statusClass = match($reg->status) {
                            'approved' => 'badge-approved',
                            'rejected' => 'badge-rejected',
                            'pending' => 'badge-pending',
                            'cancelled' => 'badge-cancelled',
                            default => ''
                        };
                        $statusLabel = $reg->status_label ?? ucfirst($reg->status);
                    @endphp
                    <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                </td>
                <td>{{ $reg->registration_type_label ?? $reg->registration_type ?? 'N/A' }}</td>
                <td>{{ $reg->archive_year ?? 'N/A' }}</td>
                <td>
                    {{ $reg->archived_at ? $reg->archived_at->format('Y-m-d') : 'N/A' }}
                    @if($reg->archivedBy)
                    <br><span class="text-muted">by: {{ $reg->archivedBy->name }}</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="text-align: center; padding: 40px;">
                    <p>No archived registrations found matching the criteria</p>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>
            This report was generated by the Hilltop Property Management System.
            Contains archived registration records from the system.
        </p>
        <p class="text-muted" style="margin-top: 5px;">
            Page {PAGE_NUM} of {PAGE_COUNT} | Generated: {{ now()->format('Y-m-d H:i:s') }}
        </p>
    </div>
</body>
</html>