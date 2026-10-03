{{-- resources/views/sanitation/approvals/already_responded.blade.php --}}

@extends('layouts.san')

@section('title', 'Already Responded')

@section('content')
<div class="min-h-[70vh] flex items-center justify-center px-4 py-12">
    <div class="card p-8 max-w-lg w-full text-center">

        {{-- Icon --}}
        <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6"
             style="background-color: rgba(59, 130, 246, 0.12);">
            <i class="fas fa-info-circle text-4xl" style="color: var(--info);"></i>
        </div>

        {{-- Title --}}
        <h1 class="text-2xl font-bold mb-2" style="color: var(--text-primary);">
            Already Responded
        </h1>

        <p class="text-sm mb-6" style="color: var(--text-secondary);">
            You have already submitted a response for this waste collection request.
            No further action is needed.
        </p>

        {{-- Status badge --}}
        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full mb-6
            @if($status === 'Approved') bg-green-100 text-green-700
            @elseif($status === 'Rejected') bg-red-100 text-red-700
            @elseif($status === 'Auto-Approved') bg-blue-100 text-blue-700
            @elseif($status === 'Expired') bg-gray-100 text-gray-700
            @else bg-gray-100 text-gray-700 @endif">
            @if($status === 'Approved')
                <i class="fas fa-check-circle"></i>
            @elseif($status === 'Rejected')
                <i class="fas fa-times-circle"></i>
            @elseif($status === 'Auto-Approved')
                <i class="fas fa-bolt"></i>
            @elseif($status === 'Expired')
                <i class="fas fa-hourglass-end"></i>
            @else
                <i class="fas fa-circle-info"></i>
            @endif
            <span class="font-medium text-sm">{{ $status }}</span>
        </div>

        {{-- Request summary --}}
        @if($request->property)
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
                    @if($request->approval_responded_at)
                        <div class="flex justify-between">
                            <span style="color: var(--text-secondary);">Responded</span>
                            <span class="font-medium" style="color: var(--text-primary);">
                                {{ $request->approval_responded_at->format('M d, Y g:i A') }}
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

        {{-- Help text --}}
        <p class="text-xs mt-6" style="color: var(--text-secondary);">
            <i class="fas fa-question-circle mr-1"></i>
            If you believe this is an error, please contact the sanitation team.
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