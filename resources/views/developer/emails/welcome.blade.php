// resources/views/developer/emails/welcome.blade.php
<!DOCTYPE html>
<html>
<head>
    <title>Developer Test Email</title>
</head>
<body>
    <h2>Welcome Template Test</h2>
    <p>This is a test of the welcome email template.</p>
    <p><strong>Recipient:</strong> {{ $email }}</p>
    <p><strong>Test Data:</strong></p>
    <ul>
        <li>Name: {{ $name ?? 'Test User' }}</li>
        <li>Timestamp: {{ now()->toDateTimeString() }}</li>
        <li>Configuration: Developer Testing</li>
    </ul>
</body>
</html>