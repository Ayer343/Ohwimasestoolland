<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmation</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f3f4f6; padding:24px 12px;">
        <tr>
            <td align="center">

                {{-- Main card --}}
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">

                    {{-- Header — green for success --}}
                    <tr>
                        <td style="background: linear-gradient(135deg, #059669 0%, #047857 100%); padding:28px 32px; color:#ffffff;">
                            <h1 style="margin:0; font-size:22px; font-weight:700; letter-spacing:-0.3px;">
                                {{ $settings->system_name }}
                            </h1>
                            <p style="margin:6px 0 0 0; font-size:14px; opacity:0.9;">
                                Payment Confirmation
                            </p>
                        </td>
                    </tr>

                    {{-- Success icon --}}
                    <tr>
                        <td align="center" style="padding:32px 32px 0 32px;">
                            <div style="display:inline-block; width:64px; height:64px; background-color:#d1fae5; border-radius:50%; line-height:64px; text-align:center;">
                                <span style="font-size:32px; color:#059669;">✓</span>
                            </div>
                        </td>
                    </tr>

                    {{-- Greeting --}}
                    <tr>
                        <td style="padding:16px 32px 0 32px; text-align:center;">
                            <h2 style="margin:0 0 8px 0; font-size:20px; color:#111827; font-weight:700;">
                                Payment Received
                            </h2>
                            <p style="margin:0 0 24px 0; font-size:15px; color:#374151; line-height:1.6;">
                                Thank you, <strong>{{ $landlord->name ?? 'Landlord' }}</strong>. We have successfully recorded your payment.
                            </p>
                        </td>
                    </tr>

                    {{-- Receipt card --}}
                    <tr>
                        <td style="padding:0 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">
                                <tr>
                                    <td style="padding:20px 24px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">

                                            <tr>
                                                <td style="padding:6px 0; font-size:13px; color:#6b7280; width:40%;">Invoice Number</td>
                                                <td style="padding:6px 0; font-size:14px; color:#111827; font-weight:600; text-align:right;">
                                                    {{ $invoice->invoice_number }}
                                                </td>
                                            </tr>

                                            <tr>
                                                <td style="padding:6px 0; font-size:13px; color:#6b7280;">Property</td>
                                                <td style="padding:6px 0; font-size:14px; color:#111827; text-align:right;">
                                                    {{ $property->property_name ?? $property->street_name ?? '—' }}
                                                </td>
                                            </tr>

                                            <tr>
                                                <td style="padding:6px 0; font-size:13px; color:#6b7280;">Period</td>
                                                <td style="padding:6px 0; font-size:14px; color:#111827; text-align:right;">
                                                    {{ $invoice->formatted_period ?? $invoice->period }}
                                                </td>
                                            </tr>

                                            @if ($invoice->payment_method)
                                                <tr>
                                                    <td style="padding:6px 0; font-size:13px; color:#6b7280;">Payment Method</td>
                                                    <td style="padding:6px 0; font-size:14px; color:#111827; text-align:right;">
                                                        {{ ucfirst(str_replace('_', ' ', $invoice->payment_method)) }}
                                                    </td>
                                                </tr>
                                            @endif

                                            @if ($invoice->payment_reference)
                                                <tr>
                                                    <td style="padding:6px 0; font-size:13px; color:#6b7280;">Reference</td>
                                                    <td style="padding:6px 0; font-size:14px; color:#111827; text-align:right; font-family: monospace;">
                                                        {{ $invoice->payment_reference }}
                                                    </td>
                                                </tr>
                                            @endif

                                            @if ($invoice->payment_date)
                                                <tr>
                                                    <td style="padding:6px 0; font-size:13px; color:#6b7280;">Payment Date</td>
                                                    <td style="padding:6px 0; font-size:14px; color:#111827; text-align:right;">
                                                        {{ $invoice->payment_date->format('F j, Y') }}
                                                    </td>
                                                </tr>
                                            @endif

                                            <tr>
                                                <td colspan="2" style="border-top:1px solid #e5e7eb; padding-top:12px;"></td>
                                            </tr>

                                            <tr>
                                                <td style="padding:6px 0; font-size:14px; color:#111827; font-weight:600;">Amount Paid</td>
                                                <td style="padding:6px 0; font-size:20px; color:#059669; font-weight:700; text-align:right;">
                                                    {{ $settings->formatAmount($invoice->paid_amount ?? $invoice->total_amount) }}
                                                </td>
                                            </tr>

                                            @if (($invoice->balance ?? 0) > 0)
                                                <tr>
                                                    <td style="padding:6px 0; font-size:13px; color:#6b7280;">Remaining Balance</td>
                                                    <td style="padding:6px 0; font-size:14px; color:#dc2626; font-weight:600; text-align:right;">
                                                        {{ $settings->formatAmount($invoice->balance) }}
                                                    </td>
                                                </tr>
                                            @endif

                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- CTA --}}
                    <tr>
                        <td align="center" style="padding:28px 32px 8px 32px;">
                            <a href="{{ route('landlord.invoices.show', $invoice) }}"
                               style="display:inline-block; background-color:#059669; color:#ffffff; font-size:15px; font-weight:600; text-decoration:none; padding:12px 32px; border-radius:6px;">
                                View Receipt
                            </a>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:20px 32px 28px 32px;">
                            <p style="margin:0; font-size:13px; color:#9ca3af; text-align:center; line-height:1.6;">
                                This is an automated confirmation. Please keep it for your records.<br>
                                &copy; {{ date('Y') }} {{ $settings->system_name }}. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>