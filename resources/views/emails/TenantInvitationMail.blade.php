<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tenant Registration Invitation</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #007bff; color: white; padding: 20px; text-align: center; }
        .content { padding: 30px; background: #f8f9fa; }
        .button { display: inline-block; background: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .property-info { background: white; padding: 20px; border-left: 4px solid #007bff; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #6c757d; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Tenant Registration Invitation</h1>
        </div>
        
        <div class="content">
            <h2>Hello {{ $user->name }},</h2>
            
            <p>You have been invited to register as a tenant for the following property:</p>
            
            <div class="property-info">
                <h3>{{ $property->property_name }}</h3>
                <p><strong>Address:</strong> {{ $property->street_name }}, {{ $property->zone }}</p>
                <p><strong>Landlord:</strong> {{ $property->landlord->name ?? 'N/A' }}</p>
            </div>
            
            <p>To complete your registration and access your tenant dashboard, please click the button below:</p>
            
            <div style="text-align: center;">
                <a href="{{ $invitation->getInvitationUrl() }}" class="button">
                    Complete Registration
                </a>
            </div>
            
            <p>Or copy and paste this link in your browser:</p>
            <p style="word-break: break-all; color: #007bff;">
                {{ $invitation->getInvitationUrl() }}
            </p>
            
            <div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;">
                <p><strong>Important:</strong> This invitation link will expire on <strong>{{ $invitation->expires_at->format('F j, Y \a\t g:i A') }}</strong></p>
            </div>
            
            <p>Once registered, you'll be able to:</p>
            <ul>
                <li>View your property details</li>
                <li>Receive rent statements</li>
                <li>Make online payments</li>
                <li>Submit maintenance requests</li>
                <li>Communicate with your landlord</li>
            </ul>
            
            <p>If you didn't expect this invitation or have any questions, please contact your landlord.</p>
        </div>
        
        <div class="footer">
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>