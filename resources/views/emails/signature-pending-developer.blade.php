{{-- resources/views/emails/signature-pending-developer.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Signature Pending - {{ $agreement->agreement_number ?? 'N/A' }}</title>
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
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content {
            padding: 30px;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Signature Pending</h1>
        </div>
        
        <div class="content">
            <h2>Dear Developer,</h2>
            
            <p>The super admin has signed the agreement <strong>{{ $agreement->agreement_number ?? 'N/A' }}</strong>.</p>
            
            <p>Your signature is still required to activate this agreement.</p>
            
            @if(isset($isPrimary) && $isPrimary)
            <p><strong>Note:</strong> This is the primary billing agreement. Once both parties sign, an invoice will be automatically generated.</p>
            @endif
            
            <p>
                <a href="{{ route('developer.billing.view-agreement-for-signing', $agreement->id) }}" class="button">
                    Sign Agreement Now →
                </a>
            </p>
            
            <p>Best regards,<br>
            <strong>{{ config('app.name', 'Billing System') }}</strong></p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name', 'Billing System') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>