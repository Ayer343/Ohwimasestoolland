<!DOCTYPE html>
<html>
<head>
    <title>Developer Email Test</title>
</head>
<body>
    <h2>✅ Developer Email Configuration Test</h2>
    
    <p><strong>Test Type:</strong> {{ $testType }}</p>
    <p><strong>Test Time:</strong> {{ $timestamp }}</p>
    
    <h3>Configuration Details:</h3>
    <ul>
        <li><strong>SMTP Host:</strong> {{ $config['host'] ?? 'Not set' }}</li>
        <li><strong>SMTP Port:</strong> {{ $config['port'] ?? 'Not set' }}</li>
        <li><strong>Username:</strong> {{ $config['username'] ?? 'Not set' }}</li>
    </ul>
    
    <p>If you're receiving this email, your developer email configuration is working correctly!</p>
    
    <hr>
    <small>This is an automated test email from your application.</small>
</body>
</html>