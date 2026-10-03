<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Registration Statistics Report</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .header { text-align: center; margin-bottom: 30px; }
        .section { margin-bottom: 25px; }
        .section-title { font-size: 18px; font-weight: bold; margin-bottom: 10px; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        .summary-card { display: inline-block; width: 23%; margin: 1%; padding: 10px; background: #f9f9f9; border-radius: 5px; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Registration Statistics Report</h1>
        <p>Generated: {{ $stats['report']['generated_at'] }}</p>
        <p>Date Range: {{ $stats['report']['date_range'] }}</p>
    </div>

    <!-- Summary Cards -->
    <div class="section">
        <div class="section-title">Summary</div>
        <div style="display: flex; flex-wrap: wrap;">
            <div class="summary-card">
                <strong>Total Registrations</strong><br>
                <span style="font-size: 24px;">{{ $stats['total_registrations'] }}</span>
            </div>
            <div class="summary-card">
                <strong>Approval Rate</strong><br>
                <span style="font-size: 24px;">
                    @php
                        $total = $stats['total_registrations'] ?: 1;
                        $approvalRate = round(($stats['by_status']['approved'] / $total) * 100, 1);
                    @endphp
                    {{ $approvalRate }}%
                </span>
            </div>
            <div class="summary-card">
                <strong>Pending Review</strong><br>
                <span style="font-size: 24px;">{{ $stats['by_status']['pending'] }}</span>
            </div>
            <div class="summary-card">
                <strong>Avg Processing Time</strong><br>
                <span style="font-size: 24px;">{{ $stats['avg_processing_time']['days'] }} days</span>
            </div>
        </div>
    </div>

    <!-- By Type -->
    <div class="section">
        <div class="section-title">By Registration Type</div>
        <table>
            <tr>
                <th>Type</th>
                <th>Count</th>
                <th>Percentage</th>
            </tr>
            <tr>
                <td>Construction</td>
                <td>{{ $stats['by_type']['construction'] }}</td>
                <td>{{ $stats['by_type']['construction_percentage'] }}%</td>
            </tr>
            <tr>
                <td>Property Capture</td>
                <td>{{ $stats['by_type']['property_capture'] }}</td>
                <td>{{ $stats['by_type']['property_percentage'] }}%</td>
            </tr>
        </table>
    </div>

    <!-- By Status -->
    <div class="section">
        <div class="section-title">By Status</div>
        <table>
            @foreach($stats['by_status'] as $status => $count)
            <tr>
                <td>{{ ucfirst(str_replace('_', ' ', $status)) }}</td>
                <td>{{ $count }}</td>
            </tr>
            @endforeach
        </table>
    </div>

    <!-- Monthly Trends -->
    @if($includeTrends)
    <div class="section">
        <div class="section-title">Monthly Trends</div>
        <table>
            <tr>
                <th>Month</th>
                <th>Total</th>
                <th>Approved</th>
                <th>Pending</th>
                <th>Rejected</th>
            </tr>
            @foreach($stats['monthly_stats'] as $month)
            <tr>
                <td>{{ Carbon\Carbon::create($month->year, $month->month, 1)->format('M Y') }}</td>
                <td>{{ $month->total }}</td>
                <td>{{ $month->approved }}</td>
                <td>{{ $month->pending }}</td>
                <td>{{ $month->rejected }}</td>
            </tr>
            @endforeach
        </table>
    </div>
    @endif

    <!-- Admin Performance -->
    @if($includeAdminStats && !empty($stats['admin_performance']))
    <div class="section">
        <div class="section-title">Admin Performance</div>
        <table>
            <tr>
                <th>Admin</th>
                <th>Approved</th>
                <th>Rejected</th>
                <th>Total</th>
                <th>Approval Rate</th>
                <th>Avg Time</th>
            </tr>
            @foreach($stats['admin_performance'] as $admin)
            @php
                $total = ($admin['approved'] ?? 0) + ($admin['rejected'] ?? 0);
                $rate = $total > 0 ? round(($admin['approved'] / $total) * 100, 1) : 0;
            @endphp
            <tr>
                <td>{{ $admin['name'] }}</td>
                <td>{{ $admin['approved'] }}</td>
                <td>{{ $admin['rejected'] }}</td>
                <td>{{ $total }}</td>
                <td>{{ $rate }}%</td>
                <td>{{ $admin['avg_processing_time'] }} hrs</td>
            </tr>
            @endforeach
        </table>
    </div>
    @endif

    <div class="footer">
        <p>Generated by {{ $stats['report']['generated_by'] }} | Page {PAGE_NUM} of {PAGE_COUNT}</p>
    </div>
</body>
</html>