{{ $greeting }}

{{ $instructions }}

PROPERTY DETAILS
================
Property: {{ $property->property_name }}
Address: {{ $property->street_name }}, {{ $property->zone }}
Property Type: {{ $propertyType }}
Registration ID: {{ $property->registration_pattern }}
@if($landlord)
Landlord: {{ $landlord->name }}
@endif
Registration Date: {{ $property->registration_date?->format('F j, Y') ?? 'N/A' }}

REGISTRATION STEPS:
===================
1. Click the link below to start registration
2. Verify your contact information
3. Review and accept terms
4. Set up your account password
5. Access your tenant dashboard

COMPLETE YOUR REGISTRATION:
===========================
{{ $actionUrl }}

⚠️ IMPORTANT: This invitation link will expire on {{ $expiresAt->format('F j, Y \a\t g:i A') }}

NEED HELP?
==========
If you have any questions or need assistance with your registration, 
please contact our support team at {{ $supportContact }}

{{ $footerText }}

---
You're receiving this email because you were registered as a tenant 
at {{ $property->property_name }}.

Privacy Policy: {{ $privacyPolicyUrl }}
Terms of Service: {{ $termsUrl }}

© {{ date('Y') }} {{ $companyName }}. All rights reserved.