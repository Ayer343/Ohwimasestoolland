{{-- resources/views/emails/invoice.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $invoice->invoice_number }}</title>
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
            text-align: center;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            color: #2c3e50;
            font-size: 28px;
        }
        .header .subtitle {
            color: #7f8c8d;
            font-size: 14px;
            margin-top: 5px;
        }
        .invoice-details {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .invoice-details table {
            width: 100%;
        }
        .invoice-details td {
            padding: 8px 0;
        }
        .invoice-details .label {
            color: #7f8c8d;
            font-weight: bold;
        }
        .invoice-details .value {
            text-align: right;
        }
        .amount {
            font-size: 24px;
            font-weight: bold;
            color: #27ae60;
        }
        .status-paid {
            color: #27ae60;
            font-weight: bold;
        }
        .status-pending {
            color: #f39c12;
            font-weight: bold;
        }
        .status-overdue {
            color: #e74c3c;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #95a5a6;
            font-size: 12px;
        }
        .button {
            display: inline-block;
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
        }
        .button:hover {
            background: #45a049;
        }
        .payment-method {
            background: #e8f5e9;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
        }
        .payment-method h4 {
            margin: 0 0 10px 0;
            color: #2e7d32;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Invoice</h1>
        <div class="subtitle">#{{ $invoice->invoice_number }}</div>
    </div>

    <div class="invoice-details">
        <table>
            <tr>
                <td class="label">Invoice Number</td>
                <td class="value">{{ $invoice->invoice_number }}</td>
            </tr>
            <tr>
                <td class="label">Issue Date</td>
                <td class="value">{{ $invoice->issue_date ? $invoice->issue_date->format('F j, Y') : date('F j, Y') }}</td>
            </tr>
            <tr>
                <td class="label">Due Date</td>
                <td class="value">{{ $invoice->due_date ? $invoice->due_date->format('F j, Y') : 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Amount</td>
                <td class="value amount">
                    {{ $invoice->currency ?? 'GHS' }} {{ number_format($invoice->amount, 2) }}
                </td>
            </tr>
            <tr>
                <td class="label">Status</td>
                <td class="value">
                    <span class="status-{{ $invoice->status }}">
                        {{ ucfirst($invoice->status) }}
                    </span>
                </td>
            </tr>
            <tr>
                <td class="label">Description</td>
                <td class="value">{{ $invoice->description }}</td>
            </tr>
            @if(isset($agreement))
            <tr>
                <td class="label">Agreement</td>
                <td class="value">{{ $agreement->agreement_number ?? 'N/A' }}</td>
            </tr>
            @endif
        </table>
    </div>

    @if(isset($agreement) && $agreement->payment_method)
    <div class="payment-method">
        <h4>Payment Instructions</h4>
        <p><strong>Payment Method:</strong> {{ ucfirst(str_replace('_', ' ', $agreement->payment_method)) }}</p>
        @if($agreement->payment_method == 'bank_transfer')
            <p><strong>Bank:</strong> {{ $agreement->payment_bank_name ?? 'Not specified' }}</p>
            <p><strong>Account Number:</strong> {{ $agreement->payment_account_number ?? 'Not specified' }}</p>
            <p><strong>Account Name:</strong> {{ $agreement->payment_account_name ?? 'Not specified' }}</p>
        @elseif($agreement->payment_method == 'mobile_money')
            <p><strong>Mobile Money Number:</strong> {{ $agreement->payment_mobile_number ?? 'Not specified' }}</p>
            <p><strong>Network:</strong> {{ ucfirst($agreement->payment_mobile_network ?? 'Not specified') }}</p>
        @elseif($agreement->payment_method == 'cash')
            <p><strong>Cash Payment</strong></p>
            <p>Payable to: {{ $agreement->payment_account_name ?? 'Developer' }}</p>
        @elseif($agreement->payment_method == 'check')
            <p><strong>Check Payment</strong></p>
            <p>Payable to: {{ $agreement->payment_account_name ?? 'Developer' }}</p>
        @endif
        <p><strong>Reference:</strong> Please use the invoice number as payment reference.</p>
    </div>
    @endif

    @if($invoice->status == 'pending' || $invoice->status == 'overdue')
    <div style="text-align: center; margin: 20px 0;">
        <a href="{{ $invoice->payment_url ?? route('developer.billing.dashboard') }}" class="button">
            Pay Invoice Now
        </a>
    </div>
    @endif

    <div class="footer">
        <p>Thank you for your business!</p>
        <p>This is a system-generated invoice. Please contact support if you have any questions.</p>
        <p>Generated on: {{ now()->format('F j, Y g:i A') }}</p>
    </div>
</body>
</html>