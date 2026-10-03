<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Signature Request</title>
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
        .header {
            background-color: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .content {
            padding: 20px;
            background-color: #f9f9f9;
        }
        .details {
            background-color: white;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #777;
            border-top: 1px solid #ddd;
        }
        .info-box {
            background-color: #e8f4fd;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid #3498db;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Billing Agreement Signature Required</h1>
        </div>
        <div class="content">
            <h2>Hello {{ $super_admin->name }},</h2>
            
            <p>{{ $developer->name }} has sent you a billing agreement that requires your attention.</p>
            
            <div class="details">
                <h3>Agreement Details:</h3>
                <p><strong>Agreement Number:</strong> {{ $agreement->agreement_number }}</p>
                <p><strong>Amount:</strong> {{ $agreement->currency }} {{ number_format($agreement->amount, 2) }}</p>
                <p><strong>Billing Frequency:</strong> {{ ucfirst($agreement->billing_frequency) }}</p>
                <p><strong>Start Date:</strong> {{ \Carbon\Carbon::parse($agreement->start_date)->format('F j, Y') }}</p>
                <p><strong>Description:</strong> {{ $agreement->description }}</p>
            </div>
            
            @if($custom_message)
            <div class="info-box">
                <p><strong>Message from {{ $developer->name }}:</strong></p>
                <p>{{ $custom_message }}</p>
            </div>
            @endif
            
            <div class="info-box">
                <p><strong>Next Steps:</strong></p>
                <p>1. Log in to the Super Admin portal</p>
                <p>2. Navigate to Billing → Agreements</p>
                <p>3. Find agreement #{{ $agreement->agreement_number }}</p>
                <p>4. Review and sign the agreement</p>
            </div>
            
            <p>If you have any questions about this agreement, please contact {{ $developer->name }} at {{ $developer->email }}.</p>
            
            <p>Thank you for your prompt attention to this matter.</p>
        </div>
        <div class="footer">
            <p>This is an automated message from the Billing Management System.</p>
            <p>Please do not reply to this email.</p>
            <p>&copy; {{ $current_year }} All rights reserved.</p>
        </div>
    </div>
</body>
</html>