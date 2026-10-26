{{-- resources/views/emails/unit-inquiry-reply.blade.php --}}
@component('mail::message')
# Reply to your inquiry

Hello {{ $inquiry->name }},

You received a reply regarding **{{ $unitLabel }}**:

> {{ $replyText }}

@if($landlord)
**From:** {{ $landlord->name }}
@endif

If you'd like to continue the conversation, please reply to this email or contact the landlord directly through our platform.

Thanks,<br>
{{ config('app.name') }}
@endcomponent