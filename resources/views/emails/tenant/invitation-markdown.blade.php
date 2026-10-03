@component('mail::message')
{{ $greeting }}

{{ $instructions }}

@component('mail::panel')
## 📋 Property Details

**Property:** {{ $property->property_name }}  
**Address:** {{ $property->street_name }}, {{ $property->zone }}  
**Property Type:** {{ $propertyType }}  
**Registration ID:** {{ $property->registration_pattern }}  
@if($landlord)
**Landlord:** {{ $landlord->name }}  
@endif
**Registration Date:** {{ $property->registration_date?->format('F j, Y') ?? 'N/A' }}
@endcomponent

## 📝 Registration Steps:
1. Click the button below to start registration
2. Verify your contact information
3. Review and accept terms
4. Set up your account password
5. Access your tenant dashboard

@component('mail::button', ['url' => $actionUrl, 'color' => 'primary'])
{{ $actionText }}
@endcomponent

**Or copy and paste this link in your browser:**  
{{ $actionUrl }}

@component('mail::panel', ['color' => 'warning'])
**⏰ Important:** This invitation link will expire on {{ $expiresAt->format('F j, Y \a\t g:i A') }}
@endcomponent

## Need Help?
If you have any questions or need assistance with your registration, please contact our support team at [{{ $supportContact }}](mailto:{{ $supportContact }}).

{{ $footerText }}

Thanks,<br>
{{ $companyName }}

---

**Privacy Policy:** [{{ $privacyPolicyUrl }}]({{ $privacyPolicyUrl }})  
**Terms of Service:** [{{ $termsUrl }}]({{ $termsUrl }})  

You're receiving this email because you were registered as a tenant at {{ $property->property_name }}.
@endcomponent