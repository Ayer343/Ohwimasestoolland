<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>You're Invited to {{ config('app.name', 'Ohwimase Stool Land') }}</title>
    <style>
        body { margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f6f9fc; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 20px; text-align: center; color: white; }
        .content { padding: 40px 20px; line-height: 1.6; color: #333333; }
        .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #6c757d; font-size: 12px; }
        .details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .token-info { background: #e8f4fd; padding: 10px; border-radius: 5px; margin: 10px 0; font-family: monospace; font-size: 12px; word-break: break-all; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>You're Invited!</h1>
            <p>Join {{ config('app.name', 'Ohwimase Stool Land') }} to get started</p>
        </div>
        
        <div class="content">
            @php
                // ==================== SAFE DATA EXTRACTION ====================
                $userName = '';
                $userEmail = '';
                $userPhone = '';
                $userType = 'User';
                $inviterName = 'Administrator';
                $customMessage = '';
                $expiresAt = '';
                $invitationUrl = '';
                $token = '';
                
                // ✅ FIX: Try multiple sources for invitation URL and token
                
                // Source 1: Direct invitation_url variable
                if (!empty($invitation_url)) {
                    $invitationUrl = $invitation_url;
                }
                
                // Source 2: invitationLink variable (from emailData)
                if (empty($invitationUrl) && !empty($invitationLink)) {
                    $invitationUrl = $invitationLink;
                }
                
                // Source 3: From invitation object's getInvitationUrl method
                if (empty($invitationUrl) && isset($invitation) && is_object($invitation)) {
                    if (method_exists($invitation, 'getInvitationUrl')) {
                        $invitationUrl = $invitation->getInvitationUrl();
                    } elseif (isset($invitation->invitation_url)) {
                        $invitationUrl = $invitation->invitation_url;
                    }
                }
                
                // Source 4: Build URL from route and token
                if (empty($invitationUrl) && !empty($token)) {
                    $invitationUrl = route('user.invitations.accept', ['token' => $token]);
                }
                
                // Source 5: Build URL from invitation object's token
                if (empty($invitationUrl) && isset($invitation) && is_object($invitation) && !empty($invitation->token)) {
                    $invitationUrl = route('user.invitations.accept', ['token' => $invitation->token]);
                    $token = $invitation->token;
                }
                
                // Source 6: Direct token variable
                if (empty($token) && !empty($invitation?->token)) {
                    $token = $invitation->token;
                }
                
                // Get user data
                if (isset($user) && is_object($user)) {
                    $userName = $user->name ?? '';
                    $userEmail = $user->email ?? '';
                    $userPhone = $user->phone ?? '';
                    $userType = $user->type_name ?? 'User';
                } elseif (isset($invitation) && is_object($invitation) && isset($invitation->user)) {
                    $userName = $invitation->user->name ?? '';
                    $userEmail = $invitation->user->email ?? '';
                    $userPhone = $invitation->user->phone ?? '';
                    $userType = $invitation->user->type_name ?? 'User';
                }
                
                // Get inviter data
                if (isset($invitation) && is_object($invitation) && isset($invitation->invitedBy)) {
                    $inviterName = $invitation->invitedBy->name ?? 'Administrator';
                } elseif (isset($invited_by_name)) {
                    $inviterName = $invited_by_name;
                }
                
                // Get other data
                if (isset($invitation) && is_object($invitation)) {
                    $customMessage = $invitation->custom_message ?? '';
                    if ($invitation->expires_at) {
                        $expiresAt = $invitation->expires_at->format('F j, Y \a\t g:i A');
                    }
                } elseif (isset($expiryDate)) {
                    $expiresAt = $expiryDate;
                }
                
                // Final fallbacks
                $userName = $userName ?: 'User';
                $userEmail = $userEmail ?: 'your email';
                
                // ✅ LOG FOR DEBUGGING (remove in production)
                \Illuminate\Support\Facades\Log::debug('Email template data', [
                    'invitation_url_found' => !empty($invitationUrl),
                    'invitation_url' => $invitationUrl,
                    'token_found' => !empty($token),
                    'has_invitation_object' => isset($invitation),
                    'has_user_object' => isset($user)
                ]);
            @endphp
            
            <h2>Hello {{ $userName }},</h2>
            
            <p>You've been invited by <strong>{{ $inviterName }}</strong> to join {{ config('app.name', 'Ohwimase Stool Land') }}.</p>
            
            @if($customMessage)
            <div class="details">
                <p style="margin: 0; font-style: italic;">"{{ $customMessage }}"</p>
            </div>
            @endif
            
            <p><strong>Your Account Details:</strong></p>
            <ul>
                <li><strong>Name:</strong> {{ $userName }}</li>
                <li><strong>Email:</strong> {{ $userEmail }}</li>
                @if($userPhone)
                <li><strong>Phone:</strong> {{ $userPhone }}</li>
                @endif
                <li><strong>User Type:</strong> {{ $userType }}</li>
            </ul>
            
            @if(!empty($invitationUrl))
            <div style="text-align: center;">
                <a href="{{ $invitationUrl }}" class="button" style="color: white; text-decoration: none;">
                    Accept Invitation & Complete Registration
                </a>
            </div>
            @else
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; text-align: center;">
                <p style="margin: 0; color: #856404;">
                    <strong>Invitation Processing:</strong> Your invitation link is being generated. You will receive it shortly.
                </p>
                @if(!empty($token))
                <p style="margin-top: 10px; font-size: 12px;">
                    <strong>Token:</strong> {{ $token }}
                </p>
                @endif
            </div>
            @endif
            
            @if(!empty($token) && empty($invitationUrl))
            <div class="token-info">
                <p><strong>Your Invitation Token:</strong></p>
                <p style="word-break: break-all;">{{ $token }}</p>
                <p>Please use this token to complete your registration: <br>
                <a href="{{ url('/register?token=' . $token) }}">{{ url('/register?token=' . $token) }}</a></p>
            </div>
            @endif
            
            <p><strong>This invitation will expire on:</strong> {{ $expiresAt ?: 'a future date' }}</p>
            
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <p style="margin: 0; color: #856404;">
                    <strong>Note:</strong> This invitation link is unique to you. Please do not share it with others.
                </p>
            </div>
        </div>
        
        <div class="footer">
            @if(!empty($invitationUrl))
            <p>If you're having trouble clicking the button, copy and paste the URL below into your web browser:</p>
            <p style="word-break: break-all; color: #667eea; background: #f8f9fa; padding: 10px; border-radius: 3px;">
                {{ $invitationUrl }}
            </p>
            @endif
            
            @if(!empty($token) && empty($invitationUrl))
            <p>Or use this direct link:</p>
            <p style="word-break: break-all; color: #667eea; background: #f8f9fa; padding: 10px; border-radius: 3px;">
                {{ url('/accept-invitation/' . $token) }}
            </p>
            @endif
            
            <p>&copy; {{ date('Y') }} {{ config('app.name', 'Ohwimase Stool Land') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>