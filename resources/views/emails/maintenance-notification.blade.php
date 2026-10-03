<!DOCTYPE html>
<html>
<head>
    <title>{{ $subject ?? 'Maintenance Notification' }}</title>
</head>
<body>
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
        <h2 style="color: #f59e0b;">🔧 System Maintenance Notification</h2>
        <p>{!! nl2br(e($content)) !!}</p>
        <hr>
        <p style="color: #6b7280; font-size: 12px;">
            This is an automated message from the system monitoring service.
        </p>
    </div>
</body>
</html>