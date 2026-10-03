<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
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
        .payment-details {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .payment-item {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .payment-item:last-child {
            border-bottom: none;
        }
        .label {
            font-weight: bold;
            color: #666;
        }
        .value {
            color: #333;
        }
        .amount {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            text-align: center;
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px 0;
        }
        .btn:hover {
            background: #5a67d8;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            color: #666;
            font-size: 12px;
        }
        .urgent {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }
        .overdue {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $isReminder ? '🔔 Payment Reminder' : '💰 Payment Request' }}</h1>
        <p>{{ $isReminder ? 'Friendly reminder about pending payment' : 'New payment request from developer' }}</p>
    </div>
    
    <div class="content">
        @if($paymentRequest->is_overdue)
        <div class="overdue">
            <strong>⚠️ OVERDUE:</strong> This payment is {{ $paymentRequest->days_overdue }} day(s) overdue
        </div>
        @elseif($paymentRequest->due_date->diffInDays(now()) <= 3)
        <div class="urgent">
            <strong>⏰ URGENT:</strong> Payment due in {{ $paymentRequest->due_date->diffInDays(now()) }} day(s)
        </div>
        @endif
        
        <div class="payment-details">
            <div class="amount">
                {{ $paymentRequest->currency }} {{ number_format($paymentRequest->amount, 2) }}
            </div>
            
            <div class="payment-item">
                <span class="label">Invoice #:</span>
                <span class="value">{{ $paymentRequest->invoice_number }}</span>
            </div>
            
            <div class="payment-item">
                <span class="label">Developer:</span>
                <span class="value">{{ $paymentRequest->developerSetting->developer_name ?? 'Unknown' }}</span>
            </div>
            
            <div class="payment-item">
                <span class="label">Category:</span>
                <span class="value">{{ $paymentRequest->category_label }}</span>
            </div>
            
            <div class="payment-item">
                <span class="label">Description:</span>
                <span class="value">{{ $paymentRequest->description }}</span>
            </div>
            
            <div class="payment-item">
                <span class="label">Due Date:</span>
                <span class="value">{{ $paymentRequest->due_date->format('F j, Y') }}</span>
            </div>
            
            <div class="payment-item">
                <span class="label">Status:</span>
                <span class="value" style="color: {{ $paymentRequest->getStatusColor() }};">
                    {{ $paymentRequest->status_label }}
                </span>
            </div>
            
            <div class="payment-item">
                <span class="label">Payment Method:</span>
                <span class="value">{{ $paymentRequest->payment_method_label }}</span>
            </div>
        </div>
        
        <div style="text-align: center;">
            <a href="{{ $action_url }}" class="btn">
                {{ $isReminder ? 'Review Payment Now' : 'View Payment Details' }}
            </a>
        </div>
        
        <p>
            <strong>Payment Instructions:</strong><br>
            Please make payment using the following details:<br><br>
            
            @if($paymentRequest->payment_method === \App\Models\AdminBillingRecord::PAYMENT_METHOD_BANK_TRANSFER)
            <strong>Bank Transfer:</strong><br>
            Bank: [Bank Name]<br>
            Account: [Account Number]<br>
            Name: [Account Name]<br>
            Reference: {{ $paymentRequest->invoice_number }}
            
            @elseif($paymentRequest->payment_method === \App\Models\AdminBillingRecord::PAYMENT_METHOD_MOBILE_MONEY)
            <strong>Mobile Money:</strong><br>
            Number: [Mobile Number]<br>
            Provider: [MTN/Vodafone/AirtelTigo]<br>
            Reference: {{ $paymentRequest->invoice_number }}
            
            @elseif($paymentRequest->payment_method === \App\Models\AdminBillingRecord::PAYMENT_METHOD_CASH)
            <strong>Cash Payment:</strong><br>
            Please arrange cash payment with the developer directly.
            @endif
        </p>
        
        @if($isReminder)
        <p style="color: #666; font-style: italic;">
            This is a reminder about the payment request above. If you have already made this payment, please ignore this reminder.
        </p>
        @endif
    </div>
    
    <div class="footer">
        <p>
            This is an automated message from the Developer Billing System.<br>
            Please do not reply to this email.
        </p>
        <p>
            © {{ date('Y') }} Developer Platform. All rights reserved.
        </p>
    </div>
</body>
</html>