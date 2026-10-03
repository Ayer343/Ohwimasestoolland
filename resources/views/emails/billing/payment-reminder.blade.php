{{-- resources/views/emails/billing/payment-reminder.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Reminder</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f4f5f7; color: #333333;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f4f5f7; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);">

                    {{-- Header --}}
                    <tr>
                        <td style="background: linear-gradient(135deg, #3B82F6 0%, #1E40AF 100%); padding: 32px 40px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 22px; font-weight: 600; letter-spacing: 0.5px;">
                                Payment Reminder
                            </h1>
                            <p style="margin: 8px 0 0 0; color: rgba(255, 255, 255, 0.85); font-size: 14px;">
                                Invoice {{ $invoiceNumber }}
                            </p>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding: 32px 40px;">
                            <p style="margin: 0 0 16px 0; font-size: 16px; line-height: 1.5;">
                                Hello {{ $recipientName }},
                            </p>

                            <p style="margin: 0 0 24px 0; font-size: 15px; line-height: 1.6; color: #555555;">
                                This is a friendly reminder that the following invoice is due soon.
                                Please complete the payment before the due date to avoid any late fees.
                            </p>

                            {{-- Invoice Summary Box --}}
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #F9FAFB; border-left: 4px solid #3B82F6; border-radius: 6px; margin: 24px 0;">
                                <tr>
                                    <td style="padding: 20px 24px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td style="padding: 6px 0; font-size: 13px; color: #6B7280; width: 45%;">Invoice Number</td>
                                                <td style="padding: 6px 0; font-size: 14px; color: #111827; font-weight: 600; text-align: right;">
                                                    {{ $invoiceNumber }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 6px 0; font-size: 13px; color: #6B7280;">Amount Due</td>
                                                <td style="padding: 6px 0; font-size: 16px; color: #111827; font-weight: 700; text-align: right;">
                                                    {{ $currency }} {{ number_format((float) $amount, 2) }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 6px 0; font-size: 13px; color: #6B7280;">Due Date</td>
                                                <td style="padding: 6px 0; font-size: 14px; color: #111827; text-align: right;">
                                                    {{ $dueDate }}
                                                </td>
                                            </tr>
                                            @if(!empty($daysRemaining))
                                            <tr>
                                                <td style="padding: 6px 0; font-size: 13px; color: #6B7280;">Days Remaining</td>
                                                <td style="padding: 6px 0; font-size: 14px; color: #EF4444; font-weight: 600; text-align: right;">
                                                    {{ $daysRemaining }} day{{ $daysRemaining == 1 ? '' : 's' }}
                                                </td>
                                            </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 24px 0 0 0; font-size: 15px; line-height: 1.6; color: #555555;">
                                If you've already made this payment, please disregard this message.
                            </p>

                            <p style="margin: 24px 0 0 0; font-size: 15px; line-height: 1.6; color: #555555;">
                                Thank you,<br>
                                <strong>{{ config('app.name') }}</strong>
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color: #F9FAFB; padding: 20px 40px; text-align: center; border-top: 1px solid #E5E7EB;">
                            <p style="margin: 0; font-size: 12px; color: #9CA3AF; line-height: 1.5;">
                                This is an automated message from {{ config('app.name') }}.<br>
                                Please do not reply directly to this email.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>