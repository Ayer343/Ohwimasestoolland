{{-- resources/views/sanitation/approvals/expired.blade.php --}}

@extends('layouts.san')

@section('title', 'Approval Window Expired')

@section('content')
<div class="min-h-[70vh] flex items-center justify-center px-4 py-12">
    <div class="card p-8 max-w-lg w-full text-center">

        {{-- Icon --}}
        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6"
             style="background-color: rgba(245, 158, 11, 0.15);">
            <i class="fas fa-hourglass-end text-4xl" style="color: #f59e0b;"></i>
        </div>

        {{-- Title --}}
        <h1 class="text-2xl font-bold mb-2" style="color: var(--text-primary);">
            Approval Window Expired
        </h1>

        <p class="text-sm mb-6" style="color: var(--text-secondary);">
            The approval window for this request has ended. As per our policy, the
            request has been <strong style="color: #22c55e;">automatically approved</strong>
            so collection services can proceed without further delay.
        </p>

        {{-- Auto-approved banner --}}
        @if(!empty($auto_approved))
            <div class="rounded-lg p-4 mb-6 text-left"
                 style="background-color: rgba(34, 197, 94, 0.08);
                        border-left: 4px solid #22c55e;">
                <div class="flex items-start gap-3">
                    <i class="fas fa-check-circle text-green-500 text-xl mt-0.5"></i>
                    <div>
                        <p class="font-semibold text-sm" style="color: var(--text-primary);">
                            Auto-Approved
                        </p>
                        <p class="text-xs mt-1" style="color: var(--text-secondary);">
                            The sanitation team has been notified and will begin services
                            shortly. You can track progress from your dashboard.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Request summary --}}
        @if($request && $request->property)
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
                            {{ $request->property->property_name ?? 'N/A' }}
                        </span>
                    </div>
                    @if($request->property->digital_address)
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Address</span>
                            <span class="font-medium" style="color: var(--text-primary);">
                                {{ $request->property->digital_address }}
                            </span>
                        </div>
                    @endif
                    @if($request->approval_expires_at)
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Expired On</span>
                            <span class="font-medium" style="color: var(--text-primary);">
                                {{ $request->approval_expires_at->format('M d, Y g:i A') }}
                            </span>
                        </div>
                    @endif
                    @if($request->approval_status_label)
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Current Status</span>
                            <span class="font-medium" style="color: #22c55e;">
                                {{ $request->approval_status_label }}
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- What happens next --}}
        <div class="text-left rounded-lg p-4 mb-6"
             style="background-color: rgba(59, 130, 246, 0.06);
                    border-left: 4px solid var(--info);">
            <p class="text-xs font-semibold mb-2" style="color: var(--text-primary);">
                <i class="fas fa-info-circle mr-1" style="color: var(--info);"></i>
                What happens next
            </p>
            <ul class="text-xs space-y-1 list-disc list-inside" style="color: var(--text-secondary);">
                <li>The sanitation team has been notified of the auto-approval.</li>
                <li>A collection schedule will be assigned based on your preferences.</li>
                <li>You can view and track requests from your dashboard.</li>
                <li>Future approval requests will still require your response.</li>
            </ul>
        </div>

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

        {{-- Tip --}}
        <p class="text-xs mt-6" style="color: var(--text-secondary);">
            <i class="fas fa-lightbulb mr-1" style="color: #f59e0b;"></i>
            Tip: Respond to future approval requests promptly to control your collection schedule.
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