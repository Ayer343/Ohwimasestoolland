<!DOCTYPE html>
<html>
<head>
    <title>Field Agent Invitation - {{ $systemName ?? config('app.name', 'Property Registration System') }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; background: white; }
        .header { background: #3b82f6; color: white; padding: 25px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 30px; background: #f9fafb; }
        .button { display: inline-block; padding: 14px 28px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; }
        .button:hover { background: #2563eb; }
        .footer { padding: 25px; text-align: center; font-size: 13px; color: #6b7280; background: #f8fafc; border-top: 1px solid #e5e7eb; }
        .assignment-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #3b82f6; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .instructions-box { background: #fef3cd; padding: 18px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #f59e0b; }
        .security-notes { background: #fef2f2; padding: 18px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #ef4444; }
        .system-info { background: #f0f9ff; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #0ea5e9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0; font-size: 28px;">Field Agent Invitation</h1>
            <p style="margin: 10px 0 0 0; opacity: 0.9; font-size: 16px;">
                {{ $systemName ?? config('app.name', 'Property Registration System') }}
            </p>
        </div>
        
        <div class="content">
            <p>Hello <strong style="color: #1f2937;">{{ $agentName }}</strong>,</p>
            
            <p>You have been invited to join as a Field Agent for our property registration system.</p>
            
            {{-- Assignment Details --}}
            @isset($planDetails)
            <div class="assignment-box">
                <h3 style="margin-top: 0; color: #1f2937; border-bottom: 2px solid #f3f4f6; padding-bottom: 10px;">Assignment Details</h3>
                <p><strong>Zone:</strong> {{ $planDetails['zone'] ?? 'Not specified' }}</p>
                @if(!empty($planDetails['section']))
                <p><strong>Section:</strong> {{ $planDetails['section'] }}</p>
                @endif
                @if(!empty($planDetails['estimated_houses']))
                <p><strong>Estimated Properties:</strong> {{ $planDetails['estimated_houses'] }}</p>
                @endif
                @if(!empty($planDetails['registration_period']))
                <p><strong>Registration Period:</strong> {{ $planDetails['registration_period'] }}</p>
                @endif
            </div>
            @endisset

            {{-- Custom Message --}}
            @if(!empty($customMessage))
            <div class="instructions-box">
                <h4 style="margin-top: 0; color: #92400e; font-size: 16px;">📋 Additional Instructions</h4>
                <p style="margin-bottom: 0; color: #92400e; font-style: italic;">"{{ $customMessage }}"</p>
            </div>
            @endif

            {{-- Call to Action --}}
            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $invitationLink }}" class="button" style="color: white;">
                    🚀 Accept Invitation & Set Up Account
                </a>
                <p style="margin-top: 15px; font-size: 14px; color: #6b7280;">
                    <strong>Invitation Link:</strong><br>
                    <a href="{{ $invitationLink }}" style="color: #3b82f6; word-break: break-all; font-size: 12px;">
                        {{ $invitationLink }}
                    </a>
                </p>
            </div>

            {{-- Expiration Information --}}
            <div class="system-info">
                <h4 style="margin-top: 0; color: #0369a1; font-size: 16px;">⏰ Invitation Expiry</h4>
                <p style="margin: 8px 0; color: #0369a1;">
                    <strong>Expires:</strong> {{ $expiryDate ?? 'Not specified' }}
                </p>
                <p style="margin: 8px 0; color: #0369a1;">
                    <strong>Days Remaining:</strong> {{ $daysUntilExpiry ?? $totalExpiryDays ?? 7 }} days
                </p>
                @if(isset($totalExpiryDays))
                <p style="margin: 8px 0; color: #0369a1; font-size: 13px;">
                    This invitation is valid for {{ $totalExpiryDays }} days from the sending date.
                </p>
                @endif
            </div>
            
            {{-- Security Notes --}}
            <div class="security-notes">
                <h4 style="margin-top: 0; color: #dc2626; font-size: 16px;">🔒 Security & Important Notes</h4>
                <ul style="color: #dc2626; padding-left: 20px; margin-bottom: 0;">
                    <li>This invitation link expires in <strong>{{ $totalExpiryDays ?? 7 }} days</strong></li>
                    <li>You will set your own secure password during account setup</li>
                    <li>Keep your login credentials confidential at all times</li>
                    <li>Do not share this invitation link with anyone else</li>
                    <li>Contact the system administrator if you did not request this invitation</li>
                </ul>
            </div>

            {{-- Contact Information --}}
            <div style="margin-top: 25px; padding: 15px; background: #f8fafc; border-radius: 6px;">
                <p style="margin: 0; color: #6b7280; font-size: 14px;">
                    <strong>Need Assistance?</strong><br>
                    If you have any questions or encounter issues, please contact:<br>
                    <strong>{{ $systemName ?? config('app.name', 'Property Registration System') }}</strong><br>
                    📧 <a href="mailto:{{ $systemEmail ?? 'support@example.com' }}" style="color: #3b82f6;">
                        {{ $systemEmail ?? 'support@example.com' }}
                    </a>
                </p>
            </div>
        </div>
        
        <div class="footer">
            <p style="margin: 0 0 10px 0;">
                This is an automated message from <strong>{{ $systemName ?? config('app.name', 'Property Registration System') }}</strong>. 
                Please do not reply directly to this email.
            </p>
            <p style="margin: 0 0 10px 0;">
                For support, contact us at: 
                <a href="mailto:{{ $systemEmail ?? 'support@example.com' }}" style="color: #3b82f6;">
                    {{ $systemEmail ?? 'support@example.com' }}
                </a>
            </p>
            <p style="margin: 0; font-size: 12px; color: #9ca3af;">
                &copy; {{ date('Y') }} {{ $systemName ?? config('app.name', 'Property Registration System') }}. All rights reserved.
            </p>
            @if(isset($totalExpiryDays))
            <p style="margin: 10px 0 0 0; font-size: 11px; color: #9ca3af;">
                Invitation valid for {{ $totalExpiryDays }} days • Sent on {{ now()->format('F j, Y \a\t g:i A') }}
            </p>
            @endif
        </div>
    </div>
</body>
</html>