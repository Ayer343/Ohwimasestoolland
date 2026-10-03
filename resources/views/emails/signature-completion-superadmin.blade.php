{{-- resources/views/emails/signature-completion-superadmin.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Agreement Signed - {{ $agreement->agreement_number ?? 'N/A' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #27ae60 0%, #219a52 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #27ae60;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box p {
            margin: 5px 0;
        }
        .badge {
            display: inline-block;
            background-color: #27ae60;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            margin-bottom: 15px;
        }
        .button {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 20px;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Agreement Successfully Signed</h1>
        </div>
        
        <div class="content">
            @if(isset($isPrimary) && $isPrimary)
                <div class="badge">⭐ Primary Billing Agreement</div>
            @endif
            
            <h2>Dear {{ $superAdmin->name ?? 'Super Admin' }},</h2>
            
            <p>Good news! The billing agreement <strong>{{ $agreement->agreement_number ?? 'N/A' }}</strong> has been fully signed and is now <strong>ACTIVE</strong>.</p>
            
            <div class="info-box">
                <p><strong>Agreement Details:</strong></p>
                <p>📄 Agreement Number: {{ $agreement->agreement_number ?? 'N/A' }}</p>
                <p>💰 Amount: {{ $agreement->currency ?? 'GHS' }} {{ number_format($agreement->amount ?? 0, 2) }}</p>
                <p>📅 Signed Date: {{ now()->format('F j, Y') }}</p>
                @if(isset($isPrimary) && $isPrimary)
                <p>⭐ <strong>You are the Primary Billing Contact for this agreement.</strong></p>
                @endif
            </div>
            
            <p><strong>What happens next?</strong></p>
            <ul>
                <li>An invoice has been generated for the current billing period</li>
                <li>You can view your agreement and invoice in the billing dashboard</li>
                <li>Payments can be recorded through the system</li>
            </ul>
            
            <p>
                <a href="{{ route('superadmin.billing.view-agreement', $agreement->id) }}" class="button">
                    View Agreement Details →
                </a>
            </p>
            
            <p>Thank you for your cooperation!</p>
            
            <p>Best regards,<br>
            <strong>{{ config('app.name', 'Billing System') }}</strong></p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name', 'Billing System') }}. All rights reserved.</p>
            <p>This is an automated message, please do not reply.</p>
        </div>
    </div>
</body>
</html>