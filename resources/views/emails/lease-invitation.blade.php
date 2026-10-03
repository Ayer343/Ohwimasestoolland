@component('mail::message')
# Lease Agreement Invitation

Hello {{ $tenant->name }},

You have been invited to sign a lease agreement for:

**Property:** {{ $property->property_name }}  
**Unit:** {{ $unit->unit_number }} @if($unit->unit_name) ({{ $unit->unit_name }}) @endif  
**Address:** {{ $property->address }}

## Lease Details:
- **Monthly Rent:** GHS {{ number_format($lease->monthly_rent, 2) }}
- **Security Deposit:** GHS {{ number_format($lease->security_deposit, 2) }}
- **Lease Start Date:** {{ $lease->start_date->format('F j, Y') }}
- **Lease End Date:** {{ $lease->end_date ? $lease->end_date->format('F j, Y') : 'Month-to-Month' }}
- **Payment Due Day:** {{ $lease->payment_due_day }} of each month

## Important Information:
- **Grace Period:** {{ $lease->grace_period_days }} days after due date
- **Late Fee:** 
@if($lease->late_fee_type === 'percentage')
{{ $lease->late_fee_percentage }}% of monthly rent
@else
GHS {{ number_format($lease->late_fee_fixed, 2) }}
@endif
- **Notice Period:** {{ $lease->notice_period_days }} days

@component('mail::button', ['url' => $signatureUrl])
Review & Sign Lease Agreement
@endcomponent

## What happens next?
1. Click the button above to review the full lease agreement
2. Read all terms and conditions carefully
3. Sign the agreement digitally
4. Your lease will become active once signed

**Note:** This invitation will expire on {{ $invitation->expires_at->format('F j, Y, g:i A') }}

@if($landlord)
**Landlord Contact:**  
{{ $landlord->name }}  
{{ $landlord->phone ?? '' }}  
{{ $landlord->email }}
@endif

If you have any questions or concerns about this lease agreement, please contact your landlord before signing.

Best regards,  
The {{ config('app.name') }} Team

@component('mail::subcopy')
This is an automated message. Please do not reply to this email.  
If you believe you received this email in error, please contact support.
@endcomponent
@endcomponent