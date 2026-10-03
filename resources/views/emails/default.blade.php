<!DOCTYPE html>
<html>
<head>
    <title>{{ $subject ?? 'Message from HSM' }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 5px; }
        .content { background: white; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; font-size: 12px; color: #666; margin-top: 20px; }
        .button { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $system_name ?? 'HSM Property System' }}</h1>
        </div>
        
        <div class="content">
            @if(isset($subject))
                <h2>{{ $subject }}</h2>
            @endif
            
            @if(isset($message))
                <p>{!! nl2br(e($message)) !!}</p>
            @else
                <p>{{ $content ?? 'No message content provided.' }}</p>
            @endif
            
            @if(isset($invitationUrl))
                <p style="text-align: center; margin: 30px 0;">
                    <a href="{{ $invitationUrl }}" class="button">Accept Invitation</a>
                </p>
            @endif
            
            @if(isset($agent) && isset($plan))
                <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 20px 0;">
                    <h3>Assignment Details</h3>
                    <p><strong>Agent:</strong> {{ $agent->name }}</p>
                    <p><strong>Zone:</strong> {{ $plan->zone }}</p>
                    <p><strong>Section:</strong> {{ $plan->section ?? 'N/A' }}</p>
                    <p><strong>Estimated Properties:</strong> {{ $plan->estimated_houses }}</p>
                </div>
            @endif
        </div>
        
        <div class="footer">
            <p>This is an automated message from {{ $system_name ?? 'HSM Property System' }}.</p>
            <p>Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>