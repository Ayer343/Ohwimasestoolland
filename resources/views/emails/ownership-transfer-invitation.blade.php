<!DOCTYPE html>
<html>
<head>
    <title>Property Ownership Transfer Invitation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #4CAF50;
        }
        .content {
            padding: 20px 0;
        }
        .property-details {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            padding-top: 20px;
            font-size: 12px;
            color: #777;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Property Ownership Transfer Invitation</h2>
        </div>
        
        <div class="content">
            <p>Dear {{ $landlord->name }},</p>
            
            <p>You have been invited to take ownership of a property through our property management system.</p>
            
            <div class="property-details">
                <h3>Property Details:</h3>
                <p><strong>Property Name:</strong> {{ $property->property_name }}</p>
                <p><strong>Address:</strong> {{ $propertyAddress }}</p>
                <p><strong>Registration Pattern:</strong> {{ $registrationPattern }}</p>
                <p><strong>Transfer Date:</strong> {{ $transferDate }}</p>
                <p><strong>Current Owner:</strong> {{ $currentOwner->name }}</p>
            </div>
            
            <p>To complete the ownership transfer process, please click the button below to register and accept ownership:</p>
            
            <div style="text-align: center;">
                <a href="{{ $invitationUrl }}" class="button">Accept Ownership Transfer</a>
            </div>
            
            <p>If the button doesn't work, copy and paste this link into your browser:</p>
            <p><a href="{{ $invitationUrl }}">{{ $invitationUrl }}</a></p>
            
            <p>This invitation will expire in 7 days. If you have any questions, please contact our support team.</p>
            
            <p>Thank you,<br>Property Management Team</p>
        </div>
        
        <div class="footer">
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>&copy; {{ date('Y') }} Property Management System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>