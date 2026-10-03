<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Shift Assignment</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 10px 10px;
            border: 1px solid #e0e0e0;
            border-top: none;
        }
        .schedule-card {
            background: white;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .schedule-detail {
            display: flex;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        .detail-label {
            font-weight: bold;
            width: 120px;
            color: #555;
        }
        .detail-value {
            flex: 1;
        }
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-day { background: #e3f2fd; color: #1976d2; }
        .badge-night { background: #e8eaf6; color: #3f51b5; }
        .badge-evening { background: #fff3e0; color: #f57c00; }
        .badge-overnight { background: #e1f5fe; color: #0288d1; }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            font-size: 12px;
            color: #777;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
        }
        .alert {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔔 New Shift Assignment</h1>
        <p>You've been assigned to a security shift</p>
    </div>
    
    <div class="content">
        <h2>Hello, {{ $userName }}!</h2>
        
        <p>You have been assigned to a security shift. Please review the details below:</p>
        
        <div class="schedule-card">
            <h3 style="margin-top: 0;">Shift Details</h3>
            
            <div class="schedule-detail">
                <span class="detail-label">📅 Date:</span>
                <span class="detail-value"><strong>{{ $date }}</strong></span>
            </div>
            
            <div class="schedule-detail">
                <span class="detail-label">⏰ Time:</span>
                <span class="detail-value"><strong>{{ $shiftTime }}</strong></span>
            </div>
            
            <div class="schedule-detail">
                <span class="detail-label">📍 Post:</span>
                <span class="detail-value">
                    <strong>{{ $postName }}</strong>
                    @if(!empty($postLocation))
                        <br><small>{{ $postLocation }}</small>
                    @endif
                </span>
            </div>
            
            <div class="schedule-detail">
                <span class="detail-label">🔄 Shift:</span>
                <span class="detail-value">
                    <strong>{{ $shiftName }}</strong>
                    @if($category)
                        <span class="badge badge-{{ $category }}">{{ ucfirst($category) }}</span>
                    @endif
                </span>
            </div>
            
            <div class="schedule-detail">
                <span class="detail-label">🌙 Overnight:</span>
                <span class="detail-value">
                    @if($isOvernight)
                        <span class="badge badge-overnight">Yes (ends next day)</span>
                    @else
                        No
                    @endif
                </span>
            </div>
            
            <div class="schedule-detail">
                <span class="detail-label">👥 Personnel:</span>
                <span class="detail-value">{{ $requiredPersonnel }} guard(s) required</span>
            </div>
            
            @if(!empty($badgeNumber))
            <div class="schedule-detail">
                <span class="detail-label">🆔 Badge #:</span>
                <span class="detail-value">{{ $badgeNumber }}</span>
            </div>
            @endif
        </div>
        
        @if(!empty($specialInstructions))
        <div class="alert">
            <strong>📝 Special Instructions:</strong>
            <p>{{ $specialInstructions }}</p>
        </div>
        @endif
        
        @if(!empty($emergencyContact))
        <div class="schedule-detail">
            <span class="detail-label">🚨 Emergency Contact:</span>
            <span class="detail-value">{{ $emergencyContact }}</span>
        </div>
        @endif
        
        <div style="background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <p style="margin: 0;"><strong>✅ What to do next:</strong></p>
            <ul style="margin-top: 10px; margin-bottom: 0;">
                <li>Arrive at least 15 minutes before your shift start time</li>
                <li>Check in with the supervisor on duty</li>
                <li>Review any handover notes from the previous shift</li>
                <li>Ensure you have all required equipment</li>
            </ul>
        </div>
        
        <p>If you have any questions or concerns, please contact your supervisor immediately.</p>
        
        <div style="text-align: center;">
            <a href="#" class="button">View Schedule Details</a>
        </div>
    </div>
    
    <div class="footer">
        <p>This is an automated message from {{ $companyName }}. Please do not reply to this email.</p>
        <p>&copy; {{ date('Y') }} {{ $companyName }}. All rights reserved.</p>
    </div>
</body>
</html>