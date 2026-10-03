<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Administrator Invitation - {{ $appName }}</title>
    <style>
        /* Reset styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            padding: 20px;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .email-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .email-header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .email-body {
            padding: 40px 30px;
        }
        
        .greeting {
            font-size: 18px;
            margin-bottom: 25px;
            color: #2d3748;
        }
        
        .greeting strong {
            color: #667eea;
        }
        
        .card {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 25px 0;
            border-radius: 0 8px 8px 0;
        }
        
        .invitation-box {
            background: white;
            border: 2px dashed #667eea;
            padding: 25px;
            text-align: center;
            margin: 30px 0;
            border-radius: 10px;
        }
        
        .invitation-url {
            color: #667eea;
            font-weight: 600;
            word-break: break-all;
            font-size: 16px;
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px 40px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            margin: 20px 0;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .detail-item {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
        }
        
        .detail-label {
            font-size: 14px;
            color: #718096;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-value {
            font-size: 16px;
            color: #2d3748;
            font-weight: 600;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
        }
        
        .warning-title {
            color: #856404;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .steps {
            background: #e8f4fd;
            border: 1px solid #b3d7ff;
            border-radius: 8px;
            padding: 25px;
            margin: 30px 0;
        }
        
        .steps h3 {
            color: #2c5282;
            margin-bottom: 15px;
        }
        
        .steps ol {
            margin-left: 20px;
        }
        
        .steps li {
            margin-bottom: 10px;
            color: #2d3748;
        }
        
        .email-footer {
            margin-top: 40px;
            padding-top: 25px;
            border-top: 1px solid #e2e8f0;
            color: #718096;
            font-size: 14px;
        }
        
        .security-notes {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
            font-size: 13px;
        }
        
        .security-notes ul {
            margin-left: 20px;
            margin-top: 10px;
        }
        
        .signature {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .signature strong {
            color: #2d3748;
            font-size: 16px;
        }
        
        .disclaimer {
            text-align: center;
            font-size: 12px;
            color: #a0aec0;
            margin-top: 20px;
        }
        
        @media (max-width: 600px) {
            .email-body {
                padding: 25px 20px;
            }
            
            .email-header {
                padding: 30px 20px;
            }
            
            .email-header h1 {
                font-size: 24px;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .button {
                display: block;
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <h1>👑 Super Administrator Invitation</h1>
            <p>{{ $appName }} - Developer Portal</p>
        </div>
        
        <!-- Body Content -->
        <div class="email-body">
            <!-- Greeting -->
            <div class="greeting">
                Hello <strong>{{ $user->name }}</strong>,
            </div>
            
            <!-- Main Message -->
            <p>You have been appointed as a <strong style="color: #667eea;">Super Administrator</strong> for <strong>{{ $appName }}</strong> by <strong>{{ $developerName }}</strong>.</p>
            
            <!-- Warning Box -->
            <div class="warning-box">
                <div class="warning-title">
                    <span>⚠️</span>
                    <span>IMPORTANT SECURITY NOTICE</span>
                </div>
                <p>This is a privileged account with <strong>full system access privileges</strong>. Please ensure you follow all security best practices and keep your credentials secure.</p>
            </div>
            
            <!-- Invitation Card -->
            <div class="card">
                <h3 style="margin: 0 0 15px 0; color: #667eea;">Account Setup Required</h3>
                <p>To complete your account setup and access the system, please use the invitation link below:</p>
            </div>
            
            <!-- Invitation Box -->
            <div class="invitation-box">
                <p style="font-weight: 600; color: #4a5568; margin-bottom: 15px;">Your Personal Invitation Link:</p>
                
                <div class="invitation-url">
                    {{ $invitationUrl }}
                </div>
                
                <p style="margin: 25px 0;">
                    <a href="{{ $invitationUrl }}" class="button">🚀 Setup My Account Now</a>
                </p>
                
                <p style="font-size: 14px; color: #718096;">
                    <em>This invitation link will expire on: <strong>{{ $expiryDate }}</strong></em>
                </p>
            </div>
            
            <!-- Account Details -->
            <div class="details-grid">
                <div class="detail-item">
                    <div class="detail-label">Full Name</div>
                    <div class="detail-value">{{ $user->name }}</div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Email Address</div>
                    <div class="detail-value">{{ $user->email }}</div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Account Type</div>
                    <div class="detail-value">Super Administrator</div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Access Level</div>
                    <div class="detail-value">Full System Access</div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Invited By</div>
                    <div class="detail-value">{{ $developerName }}</div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Invitation Expires</div>
                    <div class="detail-value">{{ $expiryDate }}</div>
                </div>
            </div>
            
            <!-- Setup Instructions -->
            <div class="steps">
                <h3>📝 Setup Instructions</h3>
                <ol>
                    <li><strong>Click the invitation link</strong> above to begin setup</li>
                    <li><strong>Verify your email address</strong> to confirm your identity</li>
                    <li><strong>Create a strong password</strong> for your account</li>
                    <li><strong>Configure two-factor authentication</strong> for enhanced security (recommended)</li>
                    <li><strong>Review and accept</strong> the terms of service</li>
                    <li><strong>Complete your profile</strong> information</li>
                    <li><strong>Access the dashboard</strong> and explore your permissions</li>
                </ol>
            </div>
            
            <!-- Footer -->
            <div class="email-footer">
                <!-- Security Notes -->
                <div class="security-notes">
                    <p><strong>Security Guidelines:</strong></p>
                    <ul>
                        <li>This invitation is personal and should not be shared with anyone</li>
                        <li>Always use a strong, unique password for your account</li>
                        <li>Enable two-factor authentication for enhanced security</li>
                        <li>Contact support immediately if you notice any suspicious activity</li>
                        <li>Keep your login credentials secure and confidential</li>
                    </ul>
                </div>
                
                <!-- Signature -->
                <div class="signature">
                    <p>If you have any questions or need assistance, please contact the development team.</p>
                    <p style="margin-top: 15px;">
                        Best regards,<br>
                        <strong>{{ $appName }} Development Team</strong>
                    </p>
                </div>
                
                <!-- Disclaimer -->
                <div class="disclaimer">
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>