<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance QR Code - {{ $post->name }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: white;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            border: 2px solid #e5e7eb;
            padding: 30px;
            border-radius: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #4f46e5;
            font-size: 28px;
            margin: 0 0 10px 0;
        }
        .header h2 {
            color: #374151;
            font-size: 20px;
            margin: 0;
        }
        .qr-section {
            text-align: center;
            margin: 30px 0;
        }
        .qr-image {
            max-width: 300px;
            height: auto;
            border: 2px solid #e5e7eb;
            padding: 10px;
            border-radius: 10px;
        }
        .instructions {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 20px;
            margin: 30px 0;
        }
        .instructions h3 {
            color: #4f46e5;
            font-size: 20px;
            margin-top: 0;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        .checkin-box {
            background: #f0fdf4;
            border-left: 4px solid #10b981;
            padding: 15px;
            margin-bottom: 20px;
        }
        .checkout-box {
            background: #fff7ed;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin-bottom: 20px;
        }
        .important-box {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 15px;
        }
        h4 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .checkin-box h4 {
            color: #10b981;
        }
        .checkout-box h4 {
            color: #f59e0b;
        }
        .important-box h4 {
            color: #ef4444;
        }
        ol {
            margin: 0;
            padding-left: 20px;
        }
        li {
            margin-bottom: 5px;
            color: #374151;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        .info-item {
            background: white;
            border: 1px solid #e5e7eb;
            padding: 10px;
            border-radius: 5px;
        }
        .info-label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 16px;
            font-weight: bold;
            color: #4f46e5;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #9ca3af;
        }
        .permanent-badge {
            background: #4f46e5;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            display: inline-block;
            margin: 10px 0;
        }
        .expired-badge {
            background: #ef4444;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            display: inline-block;
            margin: 10px 0;
        }
        .valid-badge {
            background: #10b981;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            display: inline-block;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>ATTENDANCE QR CODE</h1>
            <h2>{{ $post->name }} ({{ $post->code }})</h2>
            @if($post->latitude && $post->longitude)
                <p style="color: #10b981; margin: 5px 0;">
                    ✓ GPS Verified Location
                </p>
            @endif
        </div>

        <div class="qr-section">
            <img src="{{ public_path('storage/' . $qrCode->image_path) }}" class="qr-image" alt="Attendance QR Code">
            
            @php
                $isPermanent = $qrCode->code_type === 'static' && $qrCode->expires_at === null;
                $isExpired = $qrCode->expires_at && Carbon\Carbon::parse($qrCode->expires_at)->isPast();
            @endphp
            
            @if($isPermanent)
                <div class="permanent-badge">✓ PERMANENT QR CODE - Never Expires</div>
            @elseif($isExpired)
                <div class="expired-badge">⚠️ EXPIRED - DO NOT USE</div>
            @elseif($qrCode->expires_at)
                <div class="valid-badge">
                    ✓ Valid until: {{ Carbon\Carbon::parse($qrCode->expires_at)->format('d M Y H:i') }}
                </div>
            @endif
        </div>

        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">QR Code Type</div>
                <div class="info-value">{{ ucfirst(str_replace('_', ' ', $qrCode->code_type)) }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Status</div>
                <div class="info-value" style="color: {{ $qrCode->is_active ? '#10b981' : '#ef4444' }};">
                    {{ $qrCode->is_active ? 'ACTIVE' : 'INACTIVE' }}
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Max Uses</div>
                <div class="info-value">{{ $qrCode->max_uses ?? 'Unlimited' }}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Current Uses</div>
                <div class="info-value">{{ $qrCode->uses_count }}</div>
            </div>
        </div>

        <div class="instructions">
            <h3>📋 INSTRUCTIONS FOR SECURITY PERSONNEL</h3>
            
            <div class="checkin-box">
                <h4>✅ CHECK-IN PROCEDURE</h4>
                <ol>
                    <li>Open the official attendance scanning app on your mobile device</li>
                    <li>Select <strong>"CHECK-IN"</strong> mode from the main menu</li>
                    <li>Scan this QR code when personnel arrive at <strong>{{ $post->name }}</strong></li>
                    <li>Verify that the scanner shows <strong style="color: #10b981;">"CHECK-IN SUCCESSFUL"</strong></li>
                    <li>Confirm personnel details appear correctly on screen</li>
                </ol>
            </div>

            <div class="checkout-box">
                <h4>⬆️ CHECK-OUT PROCEDURE</h4>
                <ol>
                    <li>Switch the app to <strong>"CHECK-OUT"</strong> mode</li>
                    <li>Scan the same QR code when personnel are leaving <strong>{{ $post->name }}</strong></li>
                    <li>Verify that the scanner shows <strong style="color: #f59e0b;">"CHECK-OUT SUCCESSFUL"</strong></li>
                    <li>Confirm total hours worked are displayed correctly</li>
                </ol>
            </div>

            <div class="important-box">
                <h4>⚠️ IMPORTANT NOTES</h4>
                <ol style="list-style-type: disc;">
                    <li>Only scan this QR code at <strong>{{ $post->name }}</strong> attendance point</li>
                    <li>Ensure good lighting for successful scanning</li>
                    <li>Do not share this QR code with unauthorized personnel</li>
                    <li>Contact your supervisor immediately if the scanner shows errors</li>
                    <li>This QR code is specifically for <strong>{{ $post->name }}</strong> only</li>
                    <li>If the QR code is damaged or not scanning, request a new one from your supervisor</li>
                </ol>
            </div>
        </div>

        <div style="margin: 20px 0; padding: 15px; background: #f3f4f6; border-radius: 5px;">
            <p style="margin: 0; color: #374151; font-size: 14px;">
                <strong>QR Code ID:</strong> {{ $qrCode->code }}<br>
                <strong>Generated on:</strong> {{ $qrCode->created_at->format('d M Y H:i:s') }}<br>
                <strong>Generated by:</strong> {{ $qrCode->creator->name ?? 'System' }}
            </p>
        </div>

        <div class="footer">
            <p>This QR code is for official attendance tracking at {{ $post->name }} only.</p>
            <p>If found, please return to Security Office or contact {{ config('app.name') }} administration.</p>
            <p>© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>