<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'Tenant Registration Invitation' }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .logo {
            max-width: 150px;
            margin-bottom: 20px;
        }
        .content {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 24px;
            color: #2d3748;
            margin-bottom: 20px;
        }
        .card {
            background: #f7fafc;
            border-left: 4px solid #4299e1;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
        }
        .property-info {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .property-icon {
            font-size: 24px;
            margin-right: 15px;
            color: #4299e1;
        }
        .info-item {
            margin: 10px 0;
            color: #4a5568;
        }
        .info-label {
            font-weight: 600;
            color: #2d3748;
            margin-right: 10px;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 28px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            margin: 25px 0;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(102, 126, 234, 0.3);
        }
        .expiry-notice {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            color: #c53030;
        }
        .instructions {
            color: #4a5568;
            margin: 20px 0;
            line-height: 1.8;
        }
        .footer {
            background: #f7fafc;
            padding: 25px 30px;
            text-align: center;
            color: #718096;
            font-size: 14px;
            border-top: 1px solid #e2e8f0;
        }
        .footer-links {
            margin-top: 15px;
        }
        .footer-links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 10px;
        }
        .footer-links a:hover {
            text-decoration: underline;
        }
        .steps {
            margin: 25px 0;
            padding-left: 20px;
        }
        .step {
            margin: 10px 0;
            color: #4a5568;
        }
        .step-number {
            display: inline-block;
            background: #667eea;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            text-align: center;
            line-height: 24px;
            margin-right: 10px;
            font-size: 14px;
        }
        @media (max-width: 600px) {
            .content {
                padding: 20px 15px;
            }
            .cta-button {
                display: block;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            @if($companyLogo)
                <img src="{{ $companyLogo }}" alt="{{ $companyName }}" class="logo">
            @endif
            <h1>Tenant Registration</h1>
            <p>Welcome to {{ $companyName }}</p>
        </div>
        
        <div class="content">
            <h2 class="greeting">{{ $greeting }}</h2>
            
            <p class="instructions">{{ $instructions }}</p>
            
            <div class="card">
                <h3 style="margin-top: 0; color: #2d3748;">📋 Property Details</h3>
                
                <div class="property-info">
                    <div class="property-icon">🏠</div>
                    <div>
                        <h4 style="margin: 0; color: #2d3748;">{{ $property->property_name }}</h4>
                        <p style="margin: 5px 0 0 0; color: #718096;">
                            {{ $property->street_name }}, {{ $property->zone }}
                        </p>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Property Type:</span>
                    {{ $propertyType }}
                </div>
                
                <div class="info-item">
                    <span class="info-label">Registration ID:</span>
                    {{ $property->registration_pattern }}
                </div>
                
                @if($landlord)
                <div class="info-item">
                    <span class="info-label">Landlord:</span>
                    {{ $landlord->name }}
                </div>
                @endif
                
                <div class="info-item">
                    <span class="info-label">Registration Date:</span>
                    {{ $property->registration_date?->format('F j, Y') ?? 'N/A' }}
                </div>
            </div>
            
            <div class="instructions">
                <h3>📝 Registration Steps:</h3>
                <div class="steps">
                    <div class="step">
                        <span class="step-number">1</span> Click the button below to start registration
                    </div>
                    <div class="step">
                        <span class="step-number">2</span> Verify your contact information
                    </div>
                    <div class="step">
                        <span class="step-number">3</span> Review and accept terms
                    </div>
                    <div class="step">
                        <span class="step-number">4</span> Set up your account password
                    </div>
                    <div class="step">
                        <span class="step-number">5</span> Access your tenant dashboard
                    </div>
                </div>
            </div>
            
            <div style="text-align: center;">
                <a href="{{ $actionUrl }}" class="cta-button">
                    {{ $actionText }}
                </a>
                
                <p style="color: #718096; margin-top: 15px;">
                    Or copy and paste this link in your browser:<br>
                    <code style="background: #f7fafc; padding: 8px 12px; border-radius: 4px; word-break: break-all; display: inline-block; margin-top: 5px;">
                        {{ $actionUrl }}
                    </code>
                </p>
            </div>
            
            <div class="expiry-notice">
                <strong>⏰ Important:</strong> This invitation link will expire on 
                <strong>{{ $expiresAt->format('F j, Y \a\t g:i A') }}</strong>
            </div>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                <h4>Need Help?</h4>
                <p style="color: #718096;">
                    If you have any questions or need assistance with your registration, 
                    please contact our support team at 
                    <a href="mailto:{{ $supportContact }}" style="color: #667eea;">
                        {{ $supportContact }}
                    </a>
                </p>
            </div>
        </div>
        
        <div class="footer">
            <p>{{ $footerText }}</p>
            
            <p style="font-size: 12px; margin-top: 20px;">
                You're receiving this email because you were registered as a tenant 
                at {{ $property->property_name }}.
            </p>
            
            <div class="footer-links">
                <a href="{{ $privacyPolicyUrl }}">Privacy Policy</a>
                <a href="{{ $termsUrl }}">Terms of Service</a>
                <a href="{{ $supportContact }}">Contact Support</a>
            </div>
            
            <p style="margin-top: 20px; color: #a0aec0; font-size: 12px;">
                © {{ date('Y') }} {{ $companyName }}. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>