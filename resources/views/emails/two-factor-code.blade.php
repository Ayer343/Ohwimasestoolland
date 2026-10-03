<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>2FA Verification Code</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .code {
            font-size: 32px;
            font-weight: bold;
            text-align: center;
            padding: 20px;
            background: #f4f4f4;
            border-radius: 8px;
            letter-spacing: 5px;
            font-family: monospace;
        }
        .warning {
            color: #666;
            font-size: 12px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Two-Factor Authentication Code</h2>
        <p>Hello {{ $user->name }},</p>
        <p>Your verification code is:</p>
        <div class="code">{{ $code }}</div>
        <p>This code will expire in {{ $validity ?? 10 }} minutes.</p>
        <p>If you didn't request this code, please ignore this email.</p>
        <div class="warning">
            <strong>Security Tip:</strong> Never share this code with anyone.
        </div>
    </div>
</body>
</html>