@component('mail::message')
# Lease Agreement Signed

Hello @if($landlord) {{ $landlord->name }} @else Landlord @endif,

The lease agreement for **{{ $property->property_name }} - Unit {{ $unit->unit_number }}** has been signed by the tenant.

## Signed Lease Details:
- **Tenant:** {{ $tenant->name }}
- **Tenant Email:** {{ $tenant->email }}
- **Tenant Phone:** {{ $tenant->phone ?? 'Not provided' }}
- **Monthly Rent:** GHS {{ number_format($lease->monthly_rent, 2) }}
- **Lease Period:** {{ $lease->start_date->format('F j, Y') }} to {{ $lease->end_date ? $lease->end_date->format('F j, Y') : 'Month-to-Month' }}
- **Security Deposit:** GHS {{ number_format($lease->security_deposit, 2) }}
- **Lease Status:** {{ ucfirst($lease->status) }}

## Next Steps:
1. The lease is now active
2. First rent payment is due on {{ $lease->start_date->addDays($lease->payment_due_day-1)->format('F j, Y') }}
3. Security deposit should be collected
4. Tenant can now move in on {{ $lease->start_date->format('F j, Y') }}

You can view the signed lease agreement in your landlord dashboard.

@component('mail::button', ['url' => route('landlord.property-units.lease-management', $unit->id)])
View Lease Agreement
@endcomponent

Best regards,  
The {{ config('app.name') }} Team

@component('mail::subcopy')
This is an automated notification. Please log in to your dashboard for more details.
@endcomponent
@endcomponent