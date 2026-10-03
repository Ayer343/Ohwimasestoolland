{{-- resources/views/emails/worker-badge.blade.php --}}
@extends('layouts.email')

@section('content')
<div style="text-align: center; padding: 20px;">
    <h2 style="color: #2563eb;">Your Worker Badge</h2>
    
    <div style="background: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <p><strong>Name:</strong> {{ $workerName }}</p>
        <p><strong>Badge Number:</strong> {{ $badgeNumber }}</p>
        <p><strong>Contract:</strong> {{ $contractNumber }}</p>
        <p><strong>Valid Until:</strong> {{ $validUntil }}</p>
    </div>

    <div style="margin: 20px 0;">
        <p>Your digital worker badge is attached as a PDF.</p>
        <p style="color: #6b7280; font-size: 14px;">
            Please present this badge at security posts for entry.
            You can also access your badge at: 
            <a href="{{ $qrCodeUrl }}" style="color: #2563eb;">
                {{ $qrCodeUrl }}
            </a>
        </p>
    </div>

    <div style="margin: 30px 0; text-align: center;">
        <a href="{{ $qrCodeUrl }}" 
           style="background: #2563eb; color: white; padding: 12px 24px; 
                  border-radius: 6px; text-decoration: none; display: inline-block;">
            View Your Digital Badge
        </a>
    </div>

    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
        <p style="color: #6b7280; font-size: 12px;">
            This badge is for authorized personnel only. 
            If you did not request this badge, please contact support.
        </p>
    </div>
</div>
@endsection