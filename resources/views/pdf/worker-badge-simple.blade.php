{{-- resources/views/pdf/worker-badge-simple.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Worker Badge - {{ $badge_number ?? 'N/A' }}</title>
    
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
        <meta name="msapplication-TileColor" content="#1e293b">
    @else
        <!-- Default favicon fallback -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <meta name="msapplication-TileColor" content="#1e293b">
    @endif
    
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            padding: 0;
            background: white;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 30px;
            border: 2px solid #1e293b;
            border-radius: 12px;
            text-align: center;
        }
        h1 {
            color: #1e293b;
            font-size: 24px;
            border-bottom: 2px solid #1e293b;
            padding-bottom: 10px;
        }
        .info {
            text-align: left;
            margin: 20px 0;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .label {
            font-weight: bold;
            color: #475569;
        }
        .value {
            color: #0f172a;
        }
        .qr-text {
            font-family: monospace;
            font-size: 12px;
            word-break: break-all;
            background: #f1f5f9;
            padding: 10px;
            border-radius: 6px;
            margin: 10px 0;
        }
        .footer {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #64748b;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: bold;
        }
        .status.active {
            background: #dcfce7;
            color: #16a34a;
        }
        .status.expired {
            background: #fee2e2;
            color: #dc2626;
        }
        .status.inactive {
            background: #f1f5f9;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 WORKER BADGE</h1>
        <p style="color: #64748b; margin-top: -10px;">Construction Worker Identification (Simple Format)</p>

        <div style="margin: 20px 0; font-size: 48px;">
            👤
        </div>

        <h2 style="margin: 0;">{{ $worker_name ?? 'Unknown Worker' }}</h2>
        <p style="color: #64748b; margin: 5px 0;">{{ $job_title ?? $trade ?? 'Worker' }}</p>

        <div class="info">
            <div class="info-row">
                <span class="label">Badge Number</span>
                <span class="value"><strong>{{ $badge_number ?? 'N/A' }}</strong></span>
            </div>
            <div class="info-row">
                <span class="label">Status</span>
                <span class="value">
                    @php
                        $statusClass = 'active';
                        $statusText = 'Active';
                        if(isset($status)) {
                            if($status === 'expired') { $statusClass = 'expired'; $statusText = 'Expired'; }
                            elseif($status === 'inactive') { $statusClass = 'inactive'; $statusText = 'Inactive'; }
                        }
                    @endphp
                    <span class="status {{ $statusClass }}">{{ $statusText }}</span>
                </span>
            </div>
            <div class="info-row">
                <span class="label">Trade</span>
                <span class="value">{{ $trade ?? 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Contract</span>
                <span class="value">{{ $contract_number ?? 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Valid From</span>
                <span class="value">{{ $valid_from ?? 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Valid Until</span>
                <span class="value">{{ $valid_until ?? 'N/A' }}</span>
            </div>
            @if(isset($specialization) && $specialization)
            <div class="info-row">
                <span class="label">Specialization</span>
                <span class="value">{{ $specialization }}</span>
            </div>
            @endif
        </div>

        <div style="margin: 15px 0;">
            <div style="font-weight: bold; color: #475569; font-size: 12px; margin-bottom: 5px;">
                QR Code Reference
            </div>
            <div class="qr-text">{{ $qr_code_text ?? 'QR Code Not Available' }}</div>
        </div>

        <div class="footer">
            <div style="font-weight: 600; color: #1e293b;">{{ $company_name ?? 'Construction Company' }}</div>
            <div style="margin-top: 5px; font-size: 10px; color: #94a3b8;">
                ⚠️ This is a digital worker identification badge.
            </div>
            <div style="margin-top: 3px; font-size: 9px; color: #cbd5e1;">
                Generated: {{ $generated_at ?? now()->format('Y-m-d H:i:s') }}
            </div>
        </div>
    </div>
</body>
</html>