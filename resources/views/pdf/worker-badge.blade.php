{{-- resources/views/pdf/worker-badge.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Worker Badge</title>
    
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
        <meta name="msapplication-TileColor" content="#2563eb">
    @else
        <!-- Default favicon fallback -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        <meta name="msapplication-TileColor" content="#2563eb">
    @endif
    
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .badge {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
            border: 2px solid #2563eb;
            border-radius: 12px;
            background: white;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 10px;
        }
        .header h2 {
            color: #2563eb;
            margin: 0;
        }
        .content {
            padding: 20px 0;
        }
        .qr-code {
            text-align: center;
            margin: 20px 0;
        }
        .qr-code img {
            width: 150px;
            height: 150px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-label {
            font-weight: bold;
            color: #6b7280;
        }
        .info-value {
            color: #1f2937;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
        }
        .status-active {
            color: #16a34a;
            font-weight: bold;
        }
        .status-inactive {
            color: #dc2626;
            font-weight: bold;
        }
        .status-pending {
            color: #f59e0b;
            font-weight: bold;
        }
        .status-expired {
            color: #6b7280;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="badge">
        <div class="header">
            <h2>WORKER BADGE</h2>
            <p style="color: #6b7280; margin: 0;">Construction Worker Identification</p>
        </div>

        <div class="content">
            <div style="text-align: center; margin-bottom: 15px;">
                @if($worker_photo)
                    <img src="{{ $worker_photo }}" style="width: 80px; height: 80px; border-radius: 50; object-fit: cover;">
                @else
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: #e5e7eb; margin: 0 auto; display: flex; align-items: center; justify-content: center; font-size: 32px; color: #6b7280;">
                        <span>👤</span>
                    </div>
                @endif
            </div>

            <div class="info-row">
                <span class="info-label">Badge Number</span>
                <span class="info-value">{{ $badge_number }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Name</span>
                <span class="info-value">{{ $worker_name }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Trade</span>
                <span class="info-value">{{ $trade ?? 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Contract</span>
                <span class="info-value">{{ $contract_number }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Valid From</span>
                <span class="info-value">{{ $valid_from }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Valid Until</span>
                <span class="info-value">{{ $valid_until }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Status</span>
                <span class="info-value status-active">● Active</span>
            </div>
        </div>

        <div class="qr-code">
            @if(isset($qr_code_path) && $qr_code_path)
                <img src="{{ $qr_code_path }}" alt="QR Code">
            @else
                <div style="width: 150px; height: 150px; margin: 0 auto; background: #f3f4f6; border: 2px dashed #d1d5db; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #9ca3af;">
                    <span style="font-size: 12px;">QR Code</span>
                </div>
            @endif
            <p style="font-size: 10px; color: #6b7280; margin: 5px 0;">
                Scan to verify
            </p>
        </div>

        <div class="footer">
            <p>{{ $company_name ?? 'Construction Company' }}</p>
            <p>This badge is property of the company. Please return upon termination.</p>
        </div>
    </div>
</body>
</html>