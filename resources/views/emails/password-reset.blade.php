<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f3f4f6;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
        }
        .email-wrapper {
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .email-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);
            padding: 32px 24px;
            text-align: center;
            color: white;
        }
        .email-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .email-header p {
            margin: 8px 0 0;
            opacity: 0.9;
            font-size: 14px;
        }
        .email-body {
            padding: 32px 24px;
            color: #1f2937;
        }
        .reset-button {
            display: inline-block;
            background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);
            color: white !important;
            text-decoration: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-weight: 600;
            margin: 16px 0;
            transition: transform 0.2s ease;
        }
        .reset-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.3);
        }

        /* ✅ App Button - Different style for app deep link */
        .app-button {
            display: inline-block;
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white !important;
            text-decoration: none;
            padding: 14px 36px;
            border-radius: 8px;
            font-weight: 600;
            margin: 16px 0;
            transition: transform 0.2s ease;
            font-size: 16px;
        }
        .app-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }
        .app-button .emoji {
            margin-right: 8px;
        }

        .web-button {
            display: inline-block;
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            color: white !important;
            text-decoration: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            margin: 8px 0;
            transition: transform 0.2s ease;
            font-size: 14px;
        }
        .web-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
        }

        .button-group {
            text-align: center;
            margin: 24px 0;
        }
        .button-group .divider {
            display: block;
            margin: 12px 0;
            font-size: 13px;
            color: #9ca3af;
        }

        .info-box {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 16px;
            margin: 24px 0;
            border-radius: 8px;
            font-size: 14px;
        }
        .info-box .icon {
            margin-right: 8px;
        }

        .deep-link-box {
            background: #e0f2fe;
            border-left: 4px solid #3b82f6;
            padding: 16px;
            margin: 16px 0;
            border-radius: 8px;
            font-size: 13px;
            word-break: break-all;
        }
        .deep-link-box strong {
            color: #1e3a8a;
        }
        .deep-link-box code {
            background: #ffffff;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            word-break: break-all;
            display: block;
            margin-top: 4px;
            color: #1f2937;
        }

        .email-footer {
            background: #f9fafb;
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
        }

        @media (max-width: 600px) {
            .container { margin: 20px auto; padding: 10px; }
            .email-body { padding: 24px 16px; }
            .reset-button, .app-button, .web-button { display: block; text-align: center; width: 100%; box-sizing: border-box; }
            .button-group .divider { margin: 8px 0; }
            .deep-link-box code { font-size: 11px; }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            body { background-color: #111827; }
            .email-wrapper { background: #1f2937; }
            .email-body { color: #e5e7eb; }
            .email-footer { background: #111827; color: #9ca3af; border-top-color: #374151; }
            .info-box { background: #374151; border-left-color: #f59e0b; }
            .deep-link-box { background: #1e3a5f; border-left-color: #3b82f6; }
            .deep-link-box code { background: #1f2937; color: #e5e7eb; }
            .web-button { background: linear-gradient(135deg, #4b5563 0%, #374151 100%); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="email-wrapper">
            <!-- ============================================================ -->
            <!-- HEADER -->
            <!-- ============================================================ -->
            <div class="email-header">
                <h1>🔐 Password Reset Request</h1>
                <p>{{ config('app.name', 'Hilltop Estate Management') }}</p>
            </div>

            <!-- ============================================================ -->
            <!-- BODY -->
            <!-- ============================================================ -->
            <div class="email-body">
                <p>Hello <strong>{{ $user->name ?? 'User' }}</strong>,</p>

                <p>We received a request to reset the password for your account associated with <strong>{{ $user->email }}</strong>.</p>

                <!-- ============================================================ -->
                <!-- ✅ PRIMARY: OPEN IN APP (Deep Link) -->
                <!-- ============================================================ -->
                <div class="button-group">
                    <a href="{{ $resetUrl ?? 'myapp://reset-password?token=' . $token . '&email=' . urlencode($user->email) }}" 
                       class="app-button">
                        📱 Open in App & Reset Password
                    </a>
                    <span class="divider">— OR —</span>
                    <a href="{{ $webResetUrl ?? url('/reset-password') . '?token=' . $token . '&email=' . urlencode($user->email) }}" 
                       class="web-button">
                        🌐 Open in Browser (Fallback)
                    </a>
                </div>

                <!-- ============================================================ -->
                <!-- SECURITY NOTES -->
                <!-- ============================================================ -->
                <div class="info-box">
                    <span class="icon">🔒</span>
                    <strong>Security Note:</strong> This password reset link will expire in <strong>60 minutes</strong> for your security.
                </div>

                <p>If you did not request a password reset, please ignore this email. Your password will remain unchanged.</p>

                <!-- ============================================================ -->
                <!-- DEEP LINK INFO (For copy/paste) -->
                <!-- ============================================================ -->
                <hr style="margin: 24px 0; border: none; border-top: 1px solid #e5e7eb;">

                <p style="font-size: 14px; color: #6b7280;">
                    <strong>📋 Having trouble with the buttons?</strong><br>
                    Copy and paste one of these links into your browser or app:
                </p>

                <div class="deep-link-box">
                    <strong>📱 For App:</strong>
                    <code>{{ $resetUrl ?? 'myapp://reset-password?token=' . $token . '&email=' . urlencode($user->email) }}</code>
                </div>

                <div class="deep-link-box" style="border-left-color: #6b7280;">
                    <strong>🌐 For Browser:</strong>
                    <code>{{ $webResetUrl ?? url('/reset-password') . '?token=' . $token . '&email=' . urlencode($user->email) }}</code>
                </div>

                <!-- ============================================================ -->
                <!-- REQUEST DETAILS -->
                <!-- ============================================================ -->
                <p style="font-size: 14px; margin-top: 24px; color: #6b7280;">
                    For security reasons, this request was made from:<br>
                    <strong>IP Address:</strong> {{ request()->ip() }}<br>
                    <strong>Time:</strong> {{ now()->format('F j, Y g:i A') }}
                </p>
            </div>

            <!-- ============================================================ -->
            <!-- FOOTER -->
            <!-- ============================================================ -->
            <div class="email-footer">
                <p>&copy; {{ date('Y') }} {{ config('app.name', 'Hilltop Estate Management') }}. All rights reserved.</p>
                <p>
                    This is an automated message, please do not reply to this email.<br>
                    If you need assistance, please contact our support team.
                </p>
            </div>
        </div>
    </div>
</body>
</html>