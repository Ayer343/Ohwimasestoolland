<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Worker Badge</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 420px;
            width: 100%;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 15px;
        }
        .title {
            font-size: 24px;
            font-weight: 700;
            color: #1a2332;
            margin-bottom: 5px;
        }
        .subtitle {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 20px;
        }
        .badge-info {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        .badge-info .row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .badge-info .row:last-child {
            border-bottom: none;
        }
        .badge-info .label {
            font-weight: 600;
            color: #6b7280;
            font-size: 13px;
        }
        .badge-info .value {
            font-weight: 500;
            color: #1a2332;
            font-size: 13px;
        }
        .badge-info .value.highlight {
            color: #2563eb;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-badge.active {
            background: #dcfce7;
            color: #16a34a;
        }
        .status-badge.expired {
            background: #fee2e2;
            color: #dc2626;
        }
        .status-badge.inactive {
            background: #f1f5f9;
            color: #64748b;
        }
        .status-badge.pending {
            background: #fef3c7;
            color: #d97706;
        }
        .footer {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #94a3b8;
        }
        .btn {
            display: inline-block;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            margin: 5px;
        }
        .btn-primary {
            background: #2563eb;
            color: white;
            border: none;
        }
        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        .error {
            color: #dc2626;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔐</div>
        <div class="title">Worker Badge Verification</div>
        <div class="subtitle">Security Post Verification</div>

        @if(isset($badge) && $badge)
            <div class="badge-info">
                <div class="row">
                    <span class="label">Worker Name</span>
                    <span class="value highlight">{{ $badge->worker->full_name ?? 'N/A' }}</span>
                </div>
                <div class="row">
                    <span class="label">Badge Number</span>
                    <span class="value">{{ $badge->badge_number ?? 'N/A' }}</span>
                </div>
                <div class="row">
                    <span class="label">Trade</span>
                    <span class="value">{{ $badge->worker->trade ?? 'N/A' }}</span>
                </div>
                <div class="row">
                    <span class="label">Contract</span>
                    <span class="value">{{ $badge->contract->contract_number ?? 'N/A' }}</span>
                </div>
                <div class="row">
                    <span class="label">Valid From</span>
                    <span class="value">{{ $badge->valid_from ? $badge->valid_from->format('d M Y') : 'N/A' }}</span>
                </div>
                <div class="row">
                    <span class="label">Valid Until</span>
                    <span class="value">{{ $badge->valid_until ? $badge->valid_until->format('d M Y') : 'N/A' }}</span>
                </div>
                <div class="row">
                    <span class="label">Status</span>
                    <span class="value">
                        <span class="status-badge {{ $badge->status }}">
                            {{ ucfirst($badge->status) }}
                        </span>
                    </span>
                </div>
            </div>

            <div>
                <a href="{{ $badge->getVerificationUrl() }}" class="btn btn-primary" target="_blank">
                    🔍 Verify Badge
                </a>
            </div>
        @else
            <div class="error">
                <p style="font-size: 48px; margin: 10px 0;">❌</p>
                <p>Invalid or expired badge code.</p>
                <p style="font-size: 14px; color: #6b7280;">Please check the code and try again.</p>
            </div>
        @endif

        <div class="footer">
            <div>Security Post Verification System</div>
            <div style="margin-top: 4px;">{{ config('app.name', 'Construction Company') }}</div>
        </div>
    </div>
</body>
</html>