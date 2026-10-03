@component('mail::message')
# Witness Confirmation

Dear {{ $witnessName }},

This email confirms that you have served as a witness for the lease agreement signing by **{{ $signerName }}**.

## Agreement Details
- **Property:** {{ $unit->property->property_name }}
- **Unit:** {{ $unit->unit_number }}
- **Lease ID:** {{ $lease->id }}
- **Signing Party:** {{ ucfirst($party) }} ({{ $signerName }})
- **Witness Role:** {{ $role ?? 'Witness' }}
- **Signature Date:** {{ $signatureDate }}
- **Lease Period:** {{ $lease->start_date->format('M d, Y') }} to {{ $lease->end_date->format('M d, Y') }}

## Your Responsibilities as a Witness
As a witness to this lease agreement, you confirm that:
1. You were physically present during the signing (or virtually witnessed the digital signing)
2. The signer appeared to sign voluntarily and understand the agreement
3. You observed the signing process
4. You are not a party to this agreement

## Important Information
- This lease agreement is a legally binding document
- Your signature as a witness helps validate the authenticity of the agreement
- Keep this confirmation for your records

## Verification
If you have any concerns or did not witness this signing, please contact us immediately at:  
**{{ config('app.email_support', 'support@example.com') }}**

Thank you for serving as a witness to this important legal document.

@component('mail::button', ['url' => route('dashboard')])
View Lease Details
@endcomponent

Best regards,  
**{{ config('app.name', 'Property Management System') }}**  

*This is an automated confirmation email. Please do not reply to this message.*
@endcomponent