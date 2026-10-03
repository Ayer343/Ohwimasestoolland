{{-- resources/views/sanitation/approvals/thankyou.blade.php --}}

@extends('layouts.san')

@section('title', 'Thank You')

@php
    $isApproved = in_array($status, ['Approved', 'Auto-Approved'], true);
    $isRejected = $status === 'Rejected';

    $iconColor = $isApproved ? '#22c55e' : ($isRejected ? '#ef4444' : '#3b82f6');
    $iconBg    = $isApproved ? 'rgba(34,197,94,0.12)' : ($isRejected ? 'rgba(239,68,68,0.12)' : 'rgba(59,130,246,0.12)');
    $iconClass = $isApproved ? 'fa-check-circle' : ($isRejected ? 'fa-times-circle' : 'fa-info-circle');
@endphp

@section('content')
<div class="min-h-[70vh] flex items-center justify-center px-4 py-12">
    <div class="card p-8 max-w-xl w-full text-center">

        {{-- Icon --}}
        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6"
             style="background-color: {{ $iconBg }};">
            <i class="fas {{ $iconClass }} text-4xl" style="color: {{ $iconColor }};"></i>
        </div>

        {{-- Title --}}
        <h1 class="text-2xl font-bold mb-2" style="color: var(--text-primary);">
            @if($isApproved)
                Thank You!
            @elseif($isRejected)
                Response Recorded
            @else
                Response Received
            @endif
        </h1>

        <p class="text-sm mb-6" style="color: var(--text-secondary);">
            @if($isApproved)
                Your approval has been recorded. The sanitation team has been notified
                and will begin collection services shortly.
            @elseif($isRejected)
                Your rejection has been recorded. The sanitation team has been notified
                and will not proceed with this collection.
            @else
                Your response has been recorded successfully.
            @endif
        </p>

        {{-- Status badge --}}
        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full mb-6"
             style="background-color: {{ $iconBg }}; color: {{ $iconColor }};">
            @if($isApproved)
                <i class="fas fa-check-circle"></i>
            @elseif($isRejected)
                <i class="fas fa-times-circle"></i>
            @else
                <i class="fas fa-info-circle"></i>
            @endif
            <span class="font-medium text-sm">{{ $status }}</span>
        </div>

        {{-- What happens next (approved only) --}}
        @if($isApproved)
            <div class="text-left rounded-lg p-4 mb-6"
                 style="background-color: rgba(34,197,94,0.06);
                        border-left: 4px solid #22c55e;">
                <p class="text-xs font-semibold mb-2" style="color: var(--text-primary);">
                    <i class="fas fa-list-check mr-1" style="color: #22c55e;"></i>
                    What happens next
                </p>
                <ul class="text-xs space-y-1 list-disc list-inside" style="color: var(--text-secondary);">
                    <li>The sanitation team has been notified of your approval.</li>
                    <li>A collection schedule will be assigned for your property.</li>
                    <li>You will receive updates as the collection progresses.</li>
                    <li>You can report a full bin at any time from your dashboard.</li>
                </ul>
            </div>
        @endif

        {{-- What happens next (rejected only) --}}
        @if($isRejected)
            <div class="text-left rounded-lg p-4 mb-6"
                 style="background-color: rgba(239,68,68,0.06);
                        border-left: 4px solid #ef4444;">
                <p class="text-xs font-semibold mb-2" style="color: var(--text-primary);">
                    <i class="fas fa-info-circle mr-1" style="color: #ef4444;"></i>
                    What happens next
                </p>
                <ul class="text-xs space-y-1 list-disc list-inside" style="color: var(--text-secondary);">
                    <li>The sanitation team will not service this property.</li>
                    <li>You can re-request sanitation service at any time.</li>
                    <li>If this was a mistake, contact the sanitation team.</li>
                </ul>
            </div>
        @endif

        {{-- Request summary --}}
        @if($request && $property)
            <div class="text-left rounded-lg p-4 mb-6"
                 style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="text-xs font-medium uppercase tracking-wider mb-2"
                     style="color: var(--text-secondary);">
                    Request Summary
                </div>
                <div class="space-y-1.5 text-sm">
                    <div class="flex justify-between">
                        <span style="color: var(--text-secondary);">Property</span>
                        <span class="font-medium" style="color: var(--text-primary);">
                            {{ $property->property_name ?? 'N/A' }}
                        </span>
                    </div>
                    @if($property->digital_address)
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Address</span>
                            <span class="font-medium" style="color: var(--text-primary);">
                                {{ $property->digital_address }}
                            </span>
                        </div>
                    @endif
                    @if($request->approval_responded_at)
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Responded</span>
                            <span class="font-medium" style="color: var(--text-primary);">
                                {{ $request->approval_responded_at->format('M d, Y g:i A') }}
                            </span>
                        </div>
                    @endif
                    @if($request->rejection_reason && $isRejected)
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Reason</span>
                            <span class="font-medium text-right" style="color: var(--text-primary); max-width: 60%;">
                                {{ $request->rejection_reason }}
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Actions --}}
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ url('/') }}" class="btn-secondary justify-center">
                <i class="fas fa-home mr-2"></i> Back to Home
            </a>
            @auth
                @if(auth()->user()->isLandlord())
                    <a href="{{ route('landlord.waste.requests') }}" class="btn-primary justify-center">
                        <i class="fas fa-list mr-2"></i> View My Requests
                    </a>
                @endif
            @endauth
        </div>

        {{-- Help footer --}}
        <p class="text-xs mt-6" style="color: var(--text-secondary);">
            <i class="fas fa-headset mr-1"></i>
            Need help? Contact the sanitation team for assistance.
        </p>
    </div>
</div>
@endsection

@push('styles')
<style>
    .btn-primary, .btn-secondary {
        display: inline-flex; align-items: center;
        padding: 0.625rem 1.25rem; border-radius: 0.5rem;
        font-size: 0.875rem; font-weight: 500;
        transition: all 0.2s; border: none;
        cursor: pointer; text-decoration: none;
    }
    .btn-primary { background-color: var(--primary); color: white; }
    .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); color: white; text-decoration: none; }
    .btn-secondary {
        background-color: var(--bg-secondary); color: var(--text-primary);
        border: 1px solid var(--border-color);
    }
    .btn-secondary:hover { opacity: 0.8; color: var(--text-primary); text-decoration: none; }
    .card {
        background-color: var(--card-bg); border-radius: 0.75rem;
        border: 1px solid var(--border-color);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
</style>
@endpush