{{-- resources/views/emails/unit-inquiry-received.blade.php --}}
@component('mail::message')
# New inquiry for {{ $unitLabel }}

You've received a new inquiry from a prospective tenant.

**From:** {{ $inquiry->name }}
**Phone:** {{ $inquiry->phone }}
@if($inquiry->email)
**Email:** {{ $inquiry->email }}
@endif

**Message:**

> {{ $inquiry->message }}

@component('mail::button', ['url' => $replyUrl, 'color' => 'primary'])
Reply on the Platform
@endcomponent

---

You're receiving this because you own this listing. All replies should go through the platform so we can keep both parties safe.

Thanks,<br>
{{ config('app.name') }}
@endcomponent