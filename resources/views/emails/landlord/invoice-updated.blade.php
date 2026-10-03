<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isOverdue ? 'Overdue Invoice Updated' : 'Invoice Updated' }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f3f4f6; padding:24px 12px;">
        <tr>
            <td align="center">

                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">

                    {{-- Header — blue for regular update, red for overdue update --}}
                    <tr>
                        <td style="background: {{ $isOverdue ? 'linear-gradient(135deg, #dc2626 0%, #b91c1c 100%)' : 'linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%)' }}; padding:28px 32px; color:#ffffff;">
                            <h1 style="margin:0; font-size:22px; font-weight:700; letter-spacing:-0.3px;">
                                {{ $settings->system_name }}
                            </h1>
                            <p style="margin:6px 0 0 0; font-size:14px; opacity:0.9;">
                                {{ $isOverdue ? 'Overdue Invoice Updated' : 'Invoice Updated' }}
                            </p>
                        </td>
                    </tr>

                    {{-- Greeting --}}
                    <tr>
                        <td style="padding:32px 32px 0 32px;">
                            <p style="margin:0 0 16px 0; font-size:16px; color:#111827;">
                                Hello <strong>{{ $landlord->name ?? 'Landlord' }}</strong>,
                            </p>
                            <p style="margin:0 0 24px 0; font-size:15px; color:#374151; line-height:1.6;">
                                @if ($isOverdue)
                                    Your overdue invoice has been updated. Please review the changes below and arrange payment as soon as possible.
                                @else
                                    Your invoice has been updated. Please review the changes below.
                                @endif
                            </p>
                        </td>
                    </tr>

                    {{-- Invoice number --}}
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

                                            <tr>
                                                <td colspan="2" style="border-top:1px solid #e5e7eb; padding-top:12px;"></td>
                                            </tr>

                                            {{-- Amount change --}}
                                            <tr>
                                                <td style="padding:6px 0; font-size:13px; color:#6b7280;">Amount</td>
                                                <td style="padding:6px 0; font-size:14px; color:#111827; text-align:right;">
                                                    <span style="color:#6b7280; text-decoration:line-through;">
                                                        {{ $updateData['formatted_old_amount'] ?? $settings->formatAmount($updateData['old_amount'] ?? 0) }}
                                                    </span>
                                                    <span style="color:#6b7280; margin:0 6px;">→</span>
                                                    <strong style="color: {{ $isOverdue ? '#dc2626' : '#2563eb' }};">
                                                        {{ $updateData['formatted_new_amount'] ?? $settings->formatAmount($updateData['new_amount'] ?? 0) }}
                                                    </strong>
                                                </td>
                                            </tr>

                                            {{-- Due date change --}}
                                            <tr>
                                                <td style="padding:6px 0; font-size:13px; color:#6b7280;">Due Date</td>
                                                <td style="padding:6px 0; font-size:14px; color:#111827; text-align:right;">
                                                    <span style="color:#6b7280; text-decoration:line-through;">
                                                        {{ $updateData['old_due_date'] ?? '—' }}
                                                    </span>
                                                    <span style="color:#6b7280; margin:0 6px;">→</span>
                                                    <strong style="color: {{ $isOverdue ? '#dc2626' : '#2563eb' }};">
                                                        {{ $updateData['new_due_date'] ?? ($invoice->due_date ? $invoice->due_date->format('M d, Y') : '—') }}
                                                    </strong>
                                                </td>
                                            </tr>

                                            @if ($isOverdue)
                                                <tr>
                                                    <td colspan="2" style="border-top:1px solid #e5e7eb; padding-top:12px;"></td>
                                                </tr>
                                                <tr>
                                                    <td style="padding:6px 0; font-size:13px; color:#dc2626; font-weight:600;" colspan="2">
                                                        ⚠️ This invoice is overdue. Please arrange payment immediately.
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
                        <td align="center" style="padding:24px 32px 8px 32px;">
                            <a href="{{ route('landlord.invoices.show', $invoice) }}"
                               style="display:inline-block; background-color: {{ $isOverdue ? '#dc2626' : '#2563eb' }}; color:#ffffff; font-size:15px; font-weight:600; text-decoration:none; padding:12px 32px; border-radius:6px;">
                                View Invoice
                            </a>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:20px 32px 28px 32px;">
                            <p style="margin:0; font-size:13px; color:#9ca3af; text-align:center; line-height:1.6;">
                                If you have questions, please contact the administrator.<br>
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